<?php


namespace Composer\Autoload;

class ComposerStaticInita12e8f71196fedf215fa2c6518889148
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'WPML\\CF7\\Constants' => __DIR__ . '/../..' . '/classes/constants.php',
        'WPML\\CF7\\Language_Metabox' => __DIR__ . '/../..' . '/classes/language-metabox.php',
        'WPML\\CF7\\Locale' => __DIR__ . '/../..' . '/classes/Locale.php',
        'WPML\\CF7\\MailSettings' => __DIR__ . '/../..' . '/classes/MailSettings.php',
        'WPML\\CF7\\NativeEditUrl' => __DIR__ . '/../..' . '/classes/NativeEditUrl.php',
        'WPML\\CF7\\Shortcodes' => __DIR__ . '/../..' . '/classes/shortcodes.php',
        'WPML\\CF7\\Templates' => __DIR__ . '/../..' . '/classes/Templates.php',
        'WPML\\CF7\\TranslationEditor\\JobFilter' => __DIR__ . '/../..' . '/classes/TranslationEditor/JobFilter.php',
        'WPML\\CF7\\TranslationEditor\\TagTexts' => __DIR__ . '/../..' . '/classes/TranslationEditor/TagTexts.php',
        'WPML\\CF7\\TranslationReview' => __DIR__ . '/../..' . '/classes/TranslationReview.php',
        'WPML\\CF7\\Translations' => __DIR__ . '/../..' . '/classes/translations.php',
        'WPML\\CF7\\Turnstile' => __DIR__ . '/../..' . '/classes/Turnstile.php',
        'WPML_Core_Version_Check' => __DIR__ . '/..' . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-core-version-check.php',
        'WPML_Dependencies' => __DIR__ . '/..' . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-dependencies.php',
        'WPML_Dependencies_Default_Texts' => __DIR__ . '/..' . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-dependencies-default-texts.php',
        'WPML_Dependencies_Texts' => __DIR__ . '/..' . '/wpml-shared/wpml-lib-dependencies/src/dependencies/interface-wpml-dependencies-texts.php',
        'WPML_PHP_Version_Check' => __DIR__ . '/..' . '/wpml-shared/wpml-lib-dependencies/src/dependencies/class-wpml-php-version-check.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInita12e8f71196fedf215fa2c6518889148::$classMap;

        }, null, ClassLoader::class);
    }
}
