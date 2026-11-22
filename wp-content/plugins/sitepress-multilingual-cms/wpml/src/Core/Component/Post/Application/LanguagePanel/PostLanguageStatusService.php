<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel;

use WPML\Core\Component\Post\Application\LanguagePanel\Query\PostLanguagePanelQueryInterface;
use WPML\PHP\Exception\InvalidItemIdException;

class PostLanguageStatusService {

  private $query;

  private $filter;


  public function __construct( PostLanguagePanelQueryInterface $query, PanelDataFilterInterface $filter ) {
    $this->query  = $query;
    $this->filter = $filter;
  }


  public function get( int $postId, ?PanelRequestHints $hints = null ): array {
    $dto = $this->query->get( $postId, $hints ?? PanelRequestHints::none() );

    return $this->filter->apply( $dto->toArray(), $postId );
  }


  public function getMany( array $postIds, ?PanelRequestHints $hints = null ): array {
    $result = [];

    foreach ( $this->query->getMany( $postIds, $hints ?? PanelRequestHints::none() ) as $postId => $dto ) {
      $result[ $postId ] = $this->filter->apply( $dto->toArray(), $postId );
    }

    return $result;
  }


}
