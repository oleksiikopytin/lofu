<?php


$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);

return array(
    'Composer\\InstalledVersions' => $vendorDir . '/composer/InstalledVersions.php',
    'WPML\\CF7\\Constants' => $baseDir . '/classes/constants.php',
    'WPML\\CF7\\Language_Metabox' => $baseDir . '/classes/language-metabox.php',
    'WPML\\CF7\\Locale' => $baseDir . '/classes/Locale.php',
    'WPML\\CF7\\MailSettings' => $baseDir . '/classes/MailSettings.php',
    'WPML\\CF7\\NativeEditUrl' => $baseDir . '/classes/NativeEditUrl.php',
    'WPML\\CF7\\Shortcodes' => $baseDir . '/classes/shortcodes.php',
    'WPML\\CF7\\Templates' => $baseDir . '/classes/Templates.php',
    'WPML\\CF7\\TranslationEditor\\JobFilter' => $baseDir . '/classes/TranslationEditor/JobFilter.php',
    'WPML\\CF7\\TranslationEditor\\TagTexts' => $baseDir . '/classes/TranslationEditor/TagTexts.php',
    'WPML\\CF7\\TranslationReview' => $baseDir . '/classes/TranslationReview.php',
    'WPML\\CF7\\Translations' => $baseDir . '/classes/translations.php',
    'WPML\\CF7\\Turnstile' => $baseDir . '/classes/Turnstile.php',
    'WPML_Core_Version_Check' => $vendorDir . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-core-version-check.php',
    'WPML_Dependencies' => $vendorDir . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-dependencies.php',
    'WPML_Dependencies_Default_Texts' => $vendorDir . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-dependencies-default-texts.php',
    'WPML_Dependencies_Texts' => $vendorDir . '/wpml-shared/wpml-lib-dependencies/src/dependencies/interface-wpml-dependencies-texts.php',
    'WPML_PHP_Version_Check' => $vendorDir . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-php-version-check.php',
);
