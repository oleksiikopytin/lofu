<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\Updates;

use WPML\Core\Port\Update\ResumableUpdateInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\MethodType;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ApiInterface;

class UpdateHandler {
  const ROUTE_ID = 'updates';
  const ROUTE_PATH = '/updates';

  const ERROR_RETURNED_FALSE = 'Update returned false.';
  const ERROR_NO_PROGRESS = 'Resumable update did not advance.';

  private $_endpoint;

  private $api;

  private $repository;

  private $updatesToPerform = [];


  public function __construct( ApiInterface $api, Repository $repository ) {
    $this->api = $api;
    $this->repository = $repository;
  }


  public function endpoint() {
    if ( $this->_endpoint === null ) {
      $this->_endpoint = new Endpoint( self::ROUTE_ID, self::ROUTE_PATH );
      $this->_endpoint->setMethod( MethodType::POST );
    }

    return $this->_endpoint;
  }


  public function registerRoute( $updatesToPerform ) {
    $this->updatesToPerform = $updatesToPerform;

    $this->api->registerRoute(
      $this->endpoint(),
      [ $this, 'handle' ],
      [ $this, 'authorisation' ]
    );
  }


  public function handle( $requestData ) {
    if (
      ! isset( $requestData['update'] )
      || ! isset( $this->updatesToPerform[ $requestData['update'] ] )
    ) {
      return;
    }

    $this->doUpdate( $this->updatesToPerform[ $requestData['update'] ] );
  }


  public function doUpdate( $update ) {
    try {
      $update->tryOnlyOnce()
        ? $this->repository->setUpdateTryOnlyOnceStuck( $update )
        : $this->repository->setUpdateInProgress( $update );

      $handler = $update->handler();
      $result = $handler->update();
    } catch ( \Throwable $e ) {
      $this->setUpdateFailed( $update, get_class( $e ) . ': ' . $e->getMessage() );
      return;
    }

    if ( ! $result ) {
      $this->setUpdateFailed( $update, self::ERROR_RETURNED_FALSE );
      return;
    }

    if ( $handler instanceof ResumableUpdateInterface && ! $handler->isFinished() ) {
      $progress = $handler->progress();
      if ( $progress === $this->repository->getProgress( $update ) ) {
        $this->setUpdateFailed( $update, self::ERROR_NO_PROGRESS . ' (' . $progress . ')' );
        return;
      }

      $this->repository->setUpdateToContinue( $update, $progress );
      return;
    }

    $this->repository->setUpdateComplete( $update );
  }


  private function setUpdateFailed( $update, $error ) {
    $update->tryOnlyOnce()
      ? $this->repository->setUpdateTryOnlyOnceFailed( $update, $error )
      : $this->repository->setUpdateFailed( $update, $error );
  }


  public function authorisation() {
    return $this->api->validateRequest( $this->endpoint()->capabilities() );
  }


}
