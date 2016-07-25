<?php
/**
 * Re-work of the phpHyphenation-library from yellowgreen designbüro
 * Original: JavaScript Hyphenator 10 (Beta) by Matthias Nater
 *
 * @author Markus Birth <markus@birth-online.de>
 * @license Creative Commons Attribution-Share Alike 2.5 Switzerland
 * @link http://yellowgreen.de/hyphenation-in-web/
 */
class phpHyphenation {
    static protected $pathToPatterns = 'patterns/';
    protected $language = 'en';
    protected $patterns = array();
    protected $dictWords = array();
    protected $hyphen = '&shy;';
    protected $leftMin = 2;
    protected $rightMin = 2;
    protected $charMin = 2;
    protected $charMax = 10;
    protected $ignoreTags = array('code', 'pre', 'script', 'style');

    /**
     * Sets the directory which contains the patterns
     * @param string $path Path to the directory containing the patterns
     * @return bool TRUE on success, FALSE if the specified $path does not exist
     */
    public static function setPatternPath($path) {
        if (!is_dir($path)) return false;
        self::$pathToPatterns = $path;
        return true;
    }

    /**
     * Sets the tags to ignore (default: code, pre, script, style)
     * @param array $tags Array containing tags to ignore
     * @param bool $append Set to true to append the specified $tags to the ignore-list (default: false)
     */
    public function setIgnoreTags($tags, $append=false) {
        if (!$append) $this->ignoreTags = array();
        $this->ignoreTags = array_merge($this->ignoreTags, $tags);
    }

    /**
     * Returns the current ignore-list for tags
     * @return array Array containing tags to ignore
     */
    public function getIgnoreTags() {
        return $this->ignoreTags;
    }

    /**
     * Sets the hyphen to use. Defaults to soft-hyphen entity.
     * @param string $hyphen The hypen to use (default: <code>&shy;</code>)
     * @return bool TRUE on success, FALSE on error.
     */
    public function setHyphen($hyphen='&shy;') {
        if (strlen($hyphen) == 0) return false;
        // update hyphenation in user dict
        foreach ($this->dictWords as $key=>$value) {
            $this->dictWords[$key] = str_replace($this->hyphen, $hyphen, $value);
        }
        $this->hyphen = $hyphen;
        return true;
    }

    /**
     * Sets the hyphenation constraints.
     * @param int $leftMin Minimum letters to leave on the left side of a word (default: 2)
     * @param int $rightMin Minimum letters to leave on the right side of a word (default: 2)
     * @param int $charMin Minimum letters a word must have to be hyphenated (default: 2)
     * @param int $charMax Maximum letters to search for a hyphenation possibility (default: 10)
     */
    public function setConstraints($leftMin=2, $rightMin=2, $charMin=2, $charMax=10) {
        $this->leftMin  = $leftMin;
        $this->rightMin = $rightMin;
        $this->charMin  = $charMin;
        $this->charMax  = $charMax;
    }

    /**
     * Creates a new phpHyphenation-object. You might have to use phpHyphenation::setPatternPath() for it to find the patterns before you can instantiate the class.
     * @param string $language Language patterns to use. A file with this name has to exist in self::$pathToPatterns. (default: en)
     * @param string $hyphen Hyphen to use (default: <code>&shy;</code>)
     * @return phpHyphenation
     */
    public function __construct($language='en', $hyphen='&shy;') {
        mb_internal_encoding('utf-8');
        $this->hyphen   = $hyphen;
        if (!$this->loadLanguage($language)) return false;
    }

    /**
     * Sets a new language for hyphenation.
     * @param string $language Language patterns to use. A file with this name has to exist in $path.
     * @param string $path The path to the patterns. Defaults to self::$pathToPatterns.
     * @return bool TRUE on success, FALSE on error.
     */
    public function loadLanguage($language, $path = false) {
        if ($path === false) $path = self::$pathToPatterns;
        if (!file_exists($path . '/' . $language . '.php')) return false;
        include($path . '/' . $language . '.php');
        $this->language = $language;
        $this->patterns = $this->convertPatterns($patterns);
        return true;
    }

    /**
     * Loads the user-defined hyphenations from a file. (Format: one word per line, hyphenation locations marked by a slash ("/").)
     * @param string $filename Filename of the file containing the user defined words.
     * @param bool $append Set to TRUE to append the new words to the list. (default: false)
     * @return bool TRUE on sucess, FALSE on error.
     */
    public function loadUserDictFromFile($filename, $append=false) {
        // get userDict
        if (empty($filename) || !file_exists($filename)) return false;
        $dictionary = file($filename, FILE_IGNORE_NEW_LINES);
        return $this->loadUserDictFromArray($dictionary, $append);
    }

    /**
     * Adds user-defined hyphenations from an array. (Format: one entry per word, hyphenation locations marked by a slash ("/").)
     * @param array $userdict Array containing user defined words.
     * @param bool $append Set to TRUE to append the new words to the list. (default: false)
     * @return bool TRUE on success, FALSE on error.
     */
    public function loadUserDictFromArray($userdict, $append=false) {
        if (!is_array($userdict)) return false;
        if (!$append) $this->dictWords = array();
        foreach ($userdict as $entry) {
            $entry = mb_strtolower(trim($entry));
            $this->dictWords[str_replace('/', '', $entry)] = str_replace('/', $this->hyphen, $entry);
        }
        return true;
    }

    /**
     * Loads the patterns from a pattern file into an associative array.
     * @param string $patterns Patterns separated by a space character (" ")
     * @return array Associative array with the patterns
     */
    protected function convertPatterns($patterns) {
        $patterns = mb_split(' ', $patterns);
        $new_patterns = array();
        foreach ($patterns as $pattern) {
            $new_patterns[preg_replace('/[0-9]/', '', $pattern)] = $pattern;
        }
        return $new_patterns;
    }

    /**
     * Hyphenates a complete text and ignores HTML tags defined in $this->ignoreTags.
     * @param string $text Text to hyphenate
     * @return string Text with $this->hyphen added to the hyphenation locations
     */
    public function doHyphenation($text) {
        $result  = array();
        $tag     = '';
        $tagName = '';
        $tagJump = 0;
        $word    = '';
        $word_boundaries = "<>\t\n\r\0\x0B !\"§$%&/()=?….,;:-–_„”«»‘’'/\\‹›()[]{}*+´`^|©℗®™℠¹²³";
        $text   .= ' ';

        for ($i=0;$i<mb_strlen($text);$i++) {
            $char = mb_substr($text, $i, 1);
            if (mb_strpos($word_boundaries, $char)===false && $tag=='') {
                $word .= $char;
                continue;
            }
            if ($word != '') {
                $result[] = $this->wordHyphenation($word);
                $word = '';
            }
            if ($tag != '' || $char == '<') {
                $tag .= $char;
            }
            if ($tag != '' && $char == '>') {
#echo 'tag closed: *' . $tag . '#' . PHP_EOL;
                $tagSep  = mb_strpos($tag, ' ');
                $tagSep2 = mb_strpos($tag, '>');
                if ($tagSep === false || $tagSep2 < $tagSep) {
                    $tagSep = $tagSep2;
                }
                $tagName = mb_substr($tag, 1, $tagSep-1);
#echo 'tagName: ' . $tagName . PHP_EOL;
                if ($tagJump == 0 && in_array(mb_strtolower($tagName), $this->ignoreTags)) {
                    $tagJump = 1;
#echo 'IGNORING TAG: ' . $tagName . PHP_EOL;
                } elseif ($tagJump == 0 || mb_strtolower(mb_substr($tag, -mb_strlen($tagName)-3)) == '</'.mb_strtolower($tagName).'>') {
#echo 'Tag done: *' . $tag . '#' . PHP_EOL;
                    $result[] = $tag;
                    $tag = '';
                    $tagJump = 0;
                }
            }
            if ($tag == '' && $char != '<' && $char != '>') {
                $result[] = $char;
            }
        }
        if ($tag != '') $result[] = $tag;
        $text = join('', $result);
        return substr($text, 0, -1);
    }

    /**
     * Hyphenates a single word, i.e. inserts $this->hyphen at locations for hyphenation.
     * @param string $word Single word to hyphenate
     * @return string Hyphenated version of the word
     */
    public function wordHyphenation($word) {
        if(mb_strlen($word) < $this->charMin) return $word;
        if(mb_strpos($word, $this->hyphen) !== false) return $word;
        if(isset($this->dictWords[mb_strtolower($word)])) return $this->dictWords[mb_strtolower($word)];

        $text_word = '_' . $word . '_';
        $word_length = mb_strlen($text_word);
        $single_character = preg_split('//u', $text_word, -1, PREG_SPLIT_NO_EMPTY);
        $text_word = mb_strtolower($text_word);
        $hyphenated_word = array();
        $numb3rs = array('0' => true, '1' => true, '2' => true, '3' => true, '4' => true, '5' => true, '6' => true, '7' => true, '8' => true, '9' => true);

        for ($position=0; $position<=($word_length-$this->charMin); $position++) {
            $maxwins = min(($word_length-$position), $this->charMax);

            for ($win=$this->charMin; $win<=$maxwins; $win++) {
                if (isset($this->patterns[mb_substr($text_word, $position, $win)])) {
                    $pattern = $this->patterns[mb_substr($text_word, $position, $win)];
                    $digits = 1;
                    $pattern_length = mb_strlen($pattern);

                    for ($i=0; $i<$pattern_length; $i++) {
                        $char = $pattern[$i];
                        if (isset($numb3rs[$char])) {
                            $zero = ($i==0)?$position-1:$position+$i-$digits;
                            if (!isset($hyphenated_word[$zero]) || $hyphenated_word[$zero]!=$char) $hyphenated_word[$zero] = $char;
                            $digits++;
                        }
                    }
                }
            }
        }

        $inserted = 0;
        for ($i=$this->leftMin; $i<=(mb_strlen($word)-$this->rightMin); $i++) {
            if (isset($hyphenated_word[$i]) && $hyphenated_word[$i]%2!=0) {
                array_splice($single_character, $i+$inserted+1, 0, $this->hyphen);
                $inserted++;
            }
        }

        return implode('', array_slice($single_character, 1, -1));
    }


}
?>
