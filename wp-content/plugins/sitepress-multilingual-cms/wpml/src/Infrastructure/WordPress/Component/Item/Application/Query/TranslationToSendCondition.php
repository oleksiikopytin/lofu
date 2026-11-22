<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query;

use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Translation\Application\Query\TranslationToSendConditionInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class TranslationToSendCondition implements TranslationToSendConditionInterface {

  const JOBLESS_STATUS_GRACE = 600;

  private $queryPrepare;


  public function __construct( QueryPrepareInterface $queryPrepare ) {
    $this->queryPrepare = $queryPrepare;
  }


  public function sql( string $statusAlias ): string {
    $acceptableStatuses = TranslationStatus::NOT_TRANSLATED . ', ' . TranslationStatus::ATE_CANCELED;

    return "( {$statusAlias}.status IS NULL OR {$statusAlias}.status IN ({$acceptableStatuses}) OR {$statusAlias}.needs_update = 1 OR {$this->joblessSql( $statusAlias )} )";
  }


  public function joblessSql( string $statusAlias ): string {
    return '( ' . $statusAlias . '.status = ' . TranslationStatus::WAITING_FOR_TRANSLATOR . '
        AND ' . $statusAlias . '.timestamp < %s
        AND NOT EXISTS ( SELECT 1 FROM ' . $this->queryPrepare->prefix() . 'icl_translate_job jobless_job WHERE jobless_job.rid = ' . $statusAlias . '.rid ) )';
  }


  public function joblessCutoff(): string {
    return date( 'Y-m-d H:i:s', time() - self::JOBLESS_STATUS_GRACE );
  }


  public function joinSql( string $statusAlias ): string {
    return \WPML\TM\ATE\TranslateEverything\DiscardedAutomaticJob::joinSql( $this->queryPrepare->prefix(), $statusAlias );
  }


  public function notDiscardedSql( string $statusAlias ): string {
    return \WPML\TM\ATE\TranslateEverything\DiscardedAutomaticJob::whereSql( $statusAlias );
  }


  public function isRefusedPair( string $sourceLanguage, string $targetLanguage ): bool {
    $refusedSources = \WPML\TM\ATE\AutomaticPairSupport::refusedSourcesByTarget( [ $sourceLanguage ], [ $targetLanguage ] );

    return in_array( $sourceLanguage, $refusedSources[ $targetLanguage ] ?? [], true );
  }


}
