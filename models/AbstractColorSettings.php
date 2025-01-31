<?php

namespace humhub\modules\flexTheme\models;

use humhub\modules\flexTheme\helpers\ColorHelper;
use humhub\modules\flexTheme\helpers\FileHelper;
use Yii;

abstract class AbstractColorSettings extends \yii\base\Model
{
    /*
     * Prefix (needed for DarkMode)
     */
    public const PREFIX = '';

    public bool $autoLoadSettings = true;

    public $configurableColors = [];
    public $hasWarnings = false;

    // Main Colors (configurable)
    public const MAIN_COLORS = ['default', 'primary', 'info', 'link', 'success', 'warning', 'danger'];
    public $default;
    public $primary;
    public $info;
    public $link;
    public $success;
    public $warning;
    public $danger;

    // Text Colors (configurable)
    public const TEXT_COLORS = ['text_color_main', 'text_color_secondary', 'text_color_highlight', 'text_color_soft', 'text_color_soft2', 'text_color_soft3', 'text_color_contrast'];
    public $text_color_main;
    public $text_color_secondary;
    public $text_color_highlight;
    public $text_color_soft;
    public $text_color_soft2;
    public $text_color_soft3;
    public $text_color_contrast;

    // Background Colors (configurable)
    public const BACKGROUND_COLORS = ['background_color_main', 'background_color_secondary', 'background_color_page', 'background_color_highlight', 'background_color_highlight_soft', 'background3', 'background4'];
    public $background_color_main;
    public $background_color_secondary;
    public $background_color_page;
    public $background_color_highlight = '#daf0f3';
    public $background_color_highlight_soft = '#f2f9fb';
    public $background3;
    public $background4;

    // Special colors which were generated by Less functions lighten(), darken() or fade()
    public const SPECIAL_COLORS = ['background4__fade__50','background4__lighten__10','background4__lighten__16','background_color_main__darken__10','background_color_page__darken__5','background_color_page__darken__8','background_color_page__lighten__10','background_color_page__lighten__20','background_color_page__lighten__3','background_color_page__lighten__30','background_color_secondary__darken__5','danger__darken__10','danger__darken__5','danger__lighten__20','danger__lighten__5','default__darken__2','default__darken__5','default__lighten__2','info__darken__10','info__darken__27','info__darken__5','info__lighten__25','info__lighten__30','info__lighten__40','info__lighten__45','info__lighten__5','info__lighten__50','info__lighten__8','link__darken__2','link__fade__60','link__lighten__5','primary__darken__10','primary__darken__5','primary__lighten__10','primary__lighten__20','primary__lighten__25','primary__lighten__5','primary__lighten__8','success__darken__10','success__darken__5','success__lighten__20','success__lighten__5','text_color_highlight__fade__15','text_color_highlight__fade__30','text_color_secondary__lighten__25','warning__darken__10','warning__darken__2','warning__darken__5','warning__lighten__10','warning__lighten__20','warning__lighten__40','warning__lighten__5',];

    abstract public function getColors(): array;

    abstract protected function getColorFallback(string $color): string;

    public function __construct($autoLoadSettings = true)
    {
        $this->autoLoadSettings = $autoLoadSettings;
        parent::__construct();
    }

    public function init()
    {
        parent::init();

        $this->configurableColors = array_merge(static::MAIN_COLORS, static::TEXT_COLORS, static::BACKGROUND_COLORS);

        if ($this->autoLoadSettings) {
            $this->loadSettings();
        }
    }

    public function loadSettings()
    {
        $settings = static::getSettings();
        foreach($this->configurableColors as $color) {
            $this->$color = $settings->get(static::PREFIX . $color);
        }
    }

    public function rules(): array
    {
        return [
            [[
                'default', 'primary', 'info', 'link', 'success', 'warning', 'danger',
                'text_color_main', 'text_color_secondary', 'text_color_highlight', 'text_color_soft', 'text_color_soft2', 'text_color_soft3', 'text_color_contrast',
                'background_color_main', 'background_color_secondary', 'background_color_page', 'background_color_highlight', 'background_color_highlight_soft', 'background3', 'background4'
                ], 'validateHexColor']
        ];
    }

    public function validateHexColor(string $attribute, $params, $validator)
    {
        if (!preg_match("/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/", $this->$attribute)) {
            $this->addError($attribute, Yii::t('FlexThemeModule.admin', 'Invalid Format') . '. ' . Yii::t('FlexThemeModule.admin', 'Must be a color in hexadecimal format, like "#00aaff" or "#FA0"'));
        }
    }

    public function save(): bool
    {
        if(!$this->validate()) {
            return false;
        }

        // Save color values
        static::saveColors();

        // Calculate and save lightened, darkened and faded colors
        static::saveSpecialColors();

        // Save colors to variables file
        if (!static::saveVarsToFile()) {
            $this->hasWarnings = true;
        }

        // Update theme.css
        if (!FileHelper::updateThemeFile((static::PREFIX))) {
            $this->hasWarnings = true;
        }

        return true;
    }

    protected function saveColors(): void
    {
        $settings = static::getSettings();

        foreach ($this->configurableColors as $color) {

            $value = $this->$color;

            // Save as module settings (value can be emtpy)
            $settings->set(static::PREFIX . $color, $value);

            // Additional color saving, used by light mode to save colors as theme var
            $this->additonalColorSaving($color, $value);
        }
    }

    /*
     * Additional color saving, used by light mode to save colors as theme var
     */
    protected function additonalColorSaving(string $color, ?string $value): void
    {
    }

    protected function saveSpecialColors(): void
    {
        $settings = static::getSettings();

        foreach (static::SPECIAL_COLORS as $color) {

            // split color names into base color, manipulation function and amount of manipulation
            list($base_var, $function, $amount) = explode("__", $color);

            // Get value of base color
            $original_color = $this->$base_var;
            if (empty($original_color)) {
                // Get Base Theme Fallback (only used by light mode)
                $original_color = $this->getColorFallback($base_var);
            }

            // Calculate color value with ColorHelper functions
            if ($function == 'darken') {

                $value = ColorHelper::darken($original_color, $amount);

            } elseif ($function == 'lighten') {

                $value = ColorHelper::lighten($original_color, $amount);

            } elseif ($function == 'fade') {

                $value = ColorHelper::fade($original_color, $amount);

            } elseif ($function == 'fadeout') {

                $value = ColorHelper::fadeout($original_color, $amount);

            } else {
                $value = '';
            }

            // Save calculated value
            $settings->set(static::PREFIX . $color, $value);
        }
    }

    protected function saveVarsToFile(): bool
    {
        $colors = static::getColors();

        $vars = '';

        foreach($colors as $key => $value) {
            $vars = $vars .  '--' . $key . ':' . $value . ';';
        }

        $content = ':root {' . $vars . '}';

        return FileHelper::updateVarsFile($content, static::PREFIX);
    }

    protected function getSettings()
    {
        return Yii::$app->getModule('flex-theme')->settings;
    }
}
