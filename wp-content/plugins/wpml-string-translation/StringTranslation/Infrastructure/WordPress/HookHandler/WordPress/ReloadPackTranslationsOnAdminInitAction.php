<?php
namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\WordPress;

use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractActionHookHandler;
use WPML\StringTranslation\Infrastructure\StringCore\Lock\PackTranslationsReloadLock;
use WPML\StringTranslation\Application\StringCore\Command\LoadExistingStringTranslationsForAllStringsCommandInterface;
use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface;

class ReloadPackTranslationsOnAdminInitAction extends AbstractActionHookHandler {
	const ACTION_NAME = 'admin_init';
	const ACTION_ARGS = 0;

	private $loadExistingStringTranslationsForAllStringsCommand;

	private $settingsRepository;

	private $lock;

	private $ranInThisRequest = false;

	public function __construct(
		LoadExistingStringTranslationsForAllStringsCommandInterface $loadExistingStringTranslationsForAllStringsCommand,
		SettingsRepositoryInterface $settingsRepository,
		PackTranslationsReloadLock $lock
	) {
		$this->loadExistingStringTranslationsForAllStringsCommand = $loadExistingStringTranslationsForAllStringsCommand;
		$this->settingsRepository                                 = $settingsRepository;
		$this->lock                                               = $lock;
	}

	protected function onAction( ...$args ) {
		if ( $this->ranInThisRequest ) {
			return;
		}

		if ( ! $this->settingsRepository->isPackTranslationsReloadPending() ) {
			return;
		}

		$this->ranInThisRequest = true;

		if ( ! $this->lock->acquire() ) {
			return;
		}

		try {
			if ( ! $this->settingsRepository->claimPackTranslationsReloadPending() ) {
				return;
			}

			try {
				$this->loadExistingStringTranslationsForAllStringsCommand->run();
			} catch ( \Throwable $e ) {
				$this->settingsRepository->markPackTranslationsReloadPending();
				throw $e;
			}

			$this->settingsRepository->setNewTranslationsWereLoadedSetting();
			$this->settingsRepository->removeKeyFromSettings( SettingsRepositoryInterface::WAS_FRONTEND_VISITED_KEY );
		} finally {
			$this->lock->release();
		}
	}
}
