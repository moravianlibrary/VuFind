<?php
/**
 * Internationalization Support for VuFind
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/language_localization_support Wiki
 */

/**
 * I18N_Translator
 *
 * The I18N_Translator class handles language translations via an Array that is
 * stored in an INI file. There is 1 ini file per language and upon construction
 * of the class, the appropriate language file is loaded. The class offers
 * functionality to manage the files as well, such as creating new language
 * files and adding/deleting of existing translations. Upon destruction, the
 * file is saved.
 *
 * @category VuFind
 * @package  Support_Classes
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/language_localization_support Wiki
 */
class I18N_Translator
{
    /**
     * Language translation files path
     *
     * @var    string
     * @access public
     */
    public $path;

    /**
     * The specified language.
     *
     * @var    string
     * @access public
     */
    public $langCode;

    /**
     * An array of the translated text
     *
     * @var    array
     * @access public
     */
    public $words = array();

    /**
     * Debugging flag
     *
     * @var    bool
     * @access public
     */
    public $debug = false;

    /**
     * Error message reflecting a problem with class initialization
     * (or false if no problem).
     *
     * @var    string|bool
     * @access public
     */
    public $error = false;

    /**
     * Constructor
     *
     * @param string $path     The path to the language files
     * @param string $langCode The ISO 639-1 Language Code
     * @param bool   $debug    Are we in debug mode?
     *
     * @access public
     */
    public function __construct($path, $langCode, $debug = false)
    {
        $this->path = $path;
        $this->langCode = preg_replace('/[^\w\-]/', '', $langCode);

        if ($debug) {
            $this->debug = true;
        }

        // Load file in specified path
        if (is_dir($path)) {
            $file = $path . '/' . $this->langCode . '.ini';
            if ($this->langCode != '' && is_file($file)) {
                $this->words = $this->_parseLanguageFile($file);
                $file_override = $path . '/' . $this->langCode . '_override.ini';
                if (is_file($file_override)) {
                   $override = $this->_parseLanguageFile($file_override);
                   foreach ($override as $key => $value) {
                      $this->words[$key] = $value;
                   }
                }
            } else {
                $this->error = "Unknown language file";
            }
        } else {
            $this->error = "Cannot open $path for reading";
        }
    }

    /**
     * Parse a language file.
     *
     * @param string $file Filename to load
     *
     * @return array
     * @access private
     */
    private function _parseLanguageFile($file)
    {
        /* Old method -- use parse_ini_file; problematic due to reserved words and
         * increased strictness in PHP 5.3.
        $words = parse_ini_file($file);
        return $words;
         */
        
        // Manually parse the language file:
        $words = array();
        $contents = file($file);
        if (is_array($contents)) {
            foreach ($contents as $current) {
                // Split the string on the equals sign, keeping a max of two chunks:
                $parts = explode('=', $current, 2);
                $key = trim($parts[0]);
                if (!empty($key) && substr($key, 0, 1) != ';') {
                    // Trim outermost double quotes off the value if present:
                    if (isset($parts[1])) {
                        $value = preg_replace(
                            '/^\"?(.*?)\"?$/', '$1', trim($parts[1])
                        );

                        // Store the key/value pair (allow empty values -- sometimes
                        // we want to replace a language token with a blank string):
                        $words[$key] = $value;
                    }
                }
            }
        }
        
        return $words;
    }

    /**
     * Translate the phrase
     *
     * @param string $phrase The phrase to translate
     *
     * @return string        Translated phrase
     * @access public
     */
    public function translate($phrase)
    {
        if (isset($this->words[$phrase])) {
            return $this->words[$phrase];
        } else {
            if ($this->debug) {
                return "translate_index_not_found($phrase)";
            } else {
                return $phrase;
            }
        }
    }
}
?>