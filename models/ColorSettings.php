<?php

namespace humhub\modules\flexTheme\models;

use humhub\modules\flexTheme\helpers\ColorHelper;
use humhub\modules\flexTheme\helpers\FileHelper;
use humhub\modules\ui\view\helpers\ThemeHelper;
use humhub\modules\ui\icon\widgets\Icon;
use Yii;
use yii\base\ErrorException;

class ColorSettings extends \yii\base\Model
{
    const BASE_THEME = 'HumHub';
    const VARS_FILE = '@flex-theme/themes/FlexTheme/css/variables.css';

    public $configurableColors = [];
    
    // Main Colors (configurable)
    const MAIN_COLORS = ['default', 'primary', 'info', 'link', 'success', 'warning', 'danger'];
    public $default;
    public $primary;
    public $info;
    public $link;
    public $success;
    public $warning;
    public $danger;

    // Text Colors (configurable)
    const TEXT_COLORS = ['text_color_main', 'text_color_secondary', 'text_color_highlight', 'text_color_soft', 'text_color_soft2', 'text_color_soft3', 'text_color_contrast'];
    public $text_color_main;
    public $text_color_secondary;
    public $text_color_highlight;
    public $text_color_soft;
    public $text_color_soft2;
    public $text_color_soft3;
    public $text_color_contrast;

    // Background Colors (configurable)
    const BACKGROUND_COLORS = ['background_color_main', 'background_color_secondary', 'background_color_page', 'background_color_highlight', 'background_color_highlight_soft', 'background3', 'background4'];
    public $background_color_main;
    public $background_color_secondary;
    public $background_color_page;
    public $background_color_highlight = '#daf0f3';
    public $background_color_highlight_soft = '#f2f9fb';
    public $background3;
    public $background4;

    // Special colors which were generated by Less functions lighten(), darken() or fade()
    const SPECIAL_COLORS = ['background_color_page__darken__5','background_color_page__darken__8','background_color_page__lighten__3','background_color_secondary__darken__5','danger__darken__10','danger__darken__5','danger__lighten__20','danger__lighten__5','default__darken__2','default__darken__5','default__lighten__2','info__darken__10','info__darken__5','info__lighten__25','info__lighten__30','info__lighten__40','info__lighten__45','info__lighten__5','info__lighten__50','info__lighten__8','link__darken__2','link__fade__60','link__lighten__5','primary__darken__10','primary__darken__5','primary__lighten__10','primary__lighten__20','primary__lighten__25','primary__lighten__5','primary__lighten__8','success__darken__10','success__darken__5','success__lighten__20','success__lighten__5','text_color_secondary__lighten__25','warning__darken__10','warning__darken__2','warning__darken__5','warning__lighten__10','warning__lighten__20','warning__lighten__40','warning__lighten__5',];

    public function getColors(): array
    {
        $settings = self::getSettings();
        $base_theme = ThemeHelper::getThemeByName(self::BASE_THEME);
        $configurableColors = array_merge(self::MAIN_COLORS, self::TEXT_COLORS, self::BACKGROUND_COLORS);

        foreach ($configurableColors as $color) {
            $value = $settings->get($color);
            
            if (empty($value)) {
                $value = (new \ReflectionClass($this))->getProperty($color)->getDefaultValue();
            }
            if (empty($value)) {
                $theme_var = str_replace('_', '-', $color);
                $value = $base_theme->variable($theme_var);
            }
            $color = str_replace('_', '-', $color);
            $result[$color] = $value;
        }
        
        foreach (self::SPECIAL_COLORS as $color) {
            $value = $settings->get($color);
            $color = str_replace('_', '-', $color);
            $result[$color] = $value;
        }

        return $result;
    }

    public function init()
    {
        parent::init();

        $this->configurableColors = array_merge(self::MAIN_COLORS, self::TEXT_COLORS, self::BACKGROUND_COLORS);
        
        $settings = self::getSettings();
        foreach($this->configurableColors as $color) {
            $this->$color = $settings->get($color);
        }
    }

    public function attributeHints(): array
    {
        $hints = [];

        $base_theme = ThemeHelper::getThemeByName(self::BASE_THEME);

        foreach ($this->configurableColors as $color) {
            $theme_var = str_replace('_', '-', $color);
            $default_value = $base_theme->variable($theme_var);
            $icon = Icon::get('circle', ['color' => $default_value]);
            $hints[$color] = Yii::t('FlexThemeModule.admin', 'Default') . ': ' . '<code>' . $default_value . '</code> ' . $icon;
        }
		$icon = Icon::get('circle', ['color' => '#daf0f3']);
        $hints['background_color_highlight'] = Yii::t('FlexThemeModule.admin', 'Default') . ': ' . '<code>' . '#daf0f3' . '</code> ' . $icon;
		$icon = Icon::get('circle', ['color' => '#f2f9fb']);
		$hints['background_color_highlight_soft'] = Yii::t('FlexThemeModule.admin', 'Default') . ': ' . '<code>' . '#f2f9fb' . '</code> ' . $icon;
		
        return $hints;
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
        self::saveColors();

        // Calculate and save lightened, darkened and faded colors
        self::saveSpecialColors();

        // Save colors to variables file
        self::saveVarsToFile();

        // Update theme.css
        FileHelper::updateThemeFile();

        return true;
    }

    public function saveColors(): void
    {
        $settings = self::getSettings();

        foreach ($this->configurableColors as $color) {

            $value = $this->$color;

            // Save as module settings (value can be emtpy)
            $settings->set($color, $value);

            // Save color values as theme variables (take community theme's color if value is empty)
            $theme_var = str_replace('_', '-', $color);
            if (empty($value)) {
                $value = ThemeHelper::getThemeByName(self::BASE_THEME)->variable($theme_var);
            }
            $theme_key = 'theme.var.FlexTheme.' . $theme_var;
            Yii::$app->settings->set($theme_key, $value);
        }
    }

    public function saveSpecialColors(): void
    {
        $settings = self::getSettings();

        $special_colors = self::SPECIAL_COLORS;

        foreach ($special_colors as $color) {

            // split color names into base color, manipulation function and amount of manipulation
            list($base_var, $function, $amount) = explode("__", $color);

            // Get value of base color
            $original_color = $this->$base_var;
            if (empty($original_color)) {
                $theme_var = str_replace('_', '-', $base_var);
                $original_color = ThemeHelper::getThemeByName(self::BASE_THEME)->variable($theme_var);
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
            $settings->set($color, $value);
        }
    }

    public function saveVarsToFile(): void
    {
        $colors = self::getColors();

        $vars = '';

        foreach($colors as $key => $value) {
              $vars = $vars .  '--' . $key . ':' . $value . ';';
        }

        $content = ':root {' . $vars . '}';
        
        FileHelper::updateVarsFile($content);
    }

    private function getSettings()
    {
        return Yii::$app->getModule('flex-theme')->settings;
    }
}
