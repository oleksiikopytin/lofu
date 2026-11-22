<?php

namespace WPML\Core\Component\Translation\Application\Update\Database\Links;

use WPML\Core\Component\Translation\Application\Service\Links\AdjustTranslationsOfOriginal;
use WPML\Core\Component\Translation\Domain\Links\HandleUpdateOriginal;
use WPML\Core\Component\Translation\Domain\Links\RepositoryInterface;
use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\Port\Update\ResumableUpdateInterface;
use function WPML\PHP\Logger\error as logError;
use function WPML\PHP\Logger\notice as logNotice;

class BackfillLinksRelationships implements ResumableUpdateInterface {
  const OPTION = 'wpml-links-relationships-backfill';
  const BATCH_SIZE = 20;
  const SECONDS_PER_RUN = 20;
  const ADJUSTMENTS_PER_RUN = 20;
  const MAX_STOPS = 2;
  const TYPE_POST = 'post';

  const STATE_LAST_ID = 'lastId';
  const STATE_COLLECTED = 'collected';
  const STATE_ADJUSTED = 'adjusted';
  const STATE_CURRENT = 'current';
  const STATE_STOPS = 'stops';
  const STATE_SKIPPED = 'skipped';

  private $query;

  private $repository;

  private $handleOriginal;

  private $adjustTranslationsOfOriginal;

  private $options;

  private $finished = false;

  private $progress = '';


  public function __construct(
    LinksBackfillQueryInterface $query,
    RepositoryInterface $repository,
    HandleUpdateOriginal $handleOriginal,
    AdjustTranslationsOfOriginal $adjustTranslationsOfOriginal,
    OptionsInterface $options
  ) {
    $this->query                        = $query;
    $this->repository                   = $repository;
    $this->handleOriginal               = $handleOriginal;
    $this->adjustTranslationsOfOriginal = $adjustTranslationsOfOriginal;
    $this->options                      = $options;
  }


  public function update() {
    $this->finished = false;
    $state          = $this->readState();
    $skipped        = $this->settleAPostWhichStoppedARun( $state );
    $this->progress = (string) $state[ self::STATE_LAST_ID ];

    if ( $skipped ) {
      return true;
    }

    $startedAt = $this->now();
    $adjustedInThisRun = 0;

    while ( true ) {
      $ids = $this->query->getOriginalPostIdsWithTranslations(
        $state[ self::STATE_LAST_ID ],
        self::BATCH_SIZE
      );

      if ( $ids === false ) {
        $this->save( $state );
        return false;
      }

      foreach ( $ids as $id ) {
        $state[ self::STATE_CURRENT ] = $id;
        $this->save( $state );

        $adjusted = $this->backfill( $id );

        if ( $adjusted === null ) {
          $state[ self::STATE_SKIPPED ]++;
        } elseif ( $adjusted !== false ) {
          $state[ self::STATE_COLLECTED ]++;
          $state[ self::STATE_ADJUSTED ] += $adjusted;
          $adjustedInThisRun             += $adjusted;
        }

        $state[ self::STATE_LAST_ID ] = $id;
        $state[ self::STATE_CURRENT ] = null;
        $state[ self::STATE_STOPS ]   = 0;
        $this->progress               = (string) $id;

        if (
          $adjustedInThisRun >= self::ADJUSTMENTS_PER_RUN
          || $this->now() - $startedAt >= self::SECONDS_PER_RUN
        ) {
          $this->save( $state );
          return true;
        }
      }

      if ( count( $ids ) < self::BATCH_SIZE ) {
        break;
      }

      $this->save( $state );
    }

    $this->finished = true;
    $this->options->delete( self::OPTION );

    logNotice(
      'WPML links relationships backfill finished: relationships collected for '
      . $state[ self::STATE_COLLECTED ] . ' originals, links adjusted in '
      . $state[ self::STATE_ADJUSTED ] . ' translations, '
      . $state[ self::STATE_SKIPPED ] . ' posts skipped.'
    );

    return true;
  }


  public function isFinished() {
    return $this->finished;
  }


  public function progress() {
    return $this->progress;
  }


  private function settleAPostWhichStoppedARun( array &$state ) {
    $current = $state[ self::STATE_CURRENT ];
    if ( $current === null ) {
      return false;
    }

    $state[ self::STATE_STOPS ]++;
    if ( $state[ self::STATE_STOPS ] < self::MAX_STOPS ) {
      return false;
    }

    logError(
      'WPML links relationships backfill skipped post ' . $current
      . ': it stopped ' . $state[ self::STATE_STOPS ] . ' runs.'
    );
    $state[ self::STATE_LAST_ID ] = max( $state[ self::STATE_LAST_ID ], $current );
    $state[ self::STATE_CURRENT ] = null;
    $state[ self::STATE_STOPS ]   = 0;
    $state[ self::STATE_SKIPPED ]++;
    $this->save( $state );

    return true;
  }


  private function backfill( int $originalId ) {
    try {
      $linkedItems = $this->handleOriginal->collectIfNotCollected(
        $this->repository->get( $originalId, self::TYPE_POST )
      );

      if ( ! $linkedItems ) {
        return false;
      }

      return $this->adjustTranslationsOfOriginal->adjust( $originalId, $linkedItems );
    } catch ( \Throwable $e ) {
      logError(
        'WPML links relationships backfill skipped post ' . $originalId . ': '
        . get_class( $e ) . ': ' . $e->getMessage()
      );

      return null;
    }
  }


  private function save( array $state ) {
    $this->options->save( self::OPTION, $state, false );
  }


  private function readState() {
    $state = $this->options->get( self::OPTION, [] );
    $state = is_array( $state ) ? $state : [];

    $int = function ( string $key ) use ( $state ): int {
      return is_numeric( $state[ $key ] ?? null ) ? (int) $state[ $key ] : 0;
    };

    return [
      self::STATE_LAST_ID   => $int( self::STATE_LAST_ID ),
      self::STATE_COLLECTED => $int( self::STATE_COLLECTED ),
      self::STATE_ADJUSTED  => $int( self::STATE_ADJUSTED ),
      self::STATE_CURRENT   => is_numeric( $state[ self::STATE_CURRENT ] ?? null ) ? (int) $state[ self::STATE_CURRENT ] : null,
      self::STATE_STOPS     => $int( self::STATE_STOPS ),
      self::STATE_SKIPPED   => $int( self::STATE_SKIPPED ),
    ];
  }


  protected function now() {
    return time();
  }


}
