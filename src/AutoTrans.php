<?php

namespace AutoTrans;

use App;
use App\Http\Requests;
use File;
use Illuminate\Console\Command;

/**
 * Class AutoTrans
 * @package Sabloger/laravel-auto-trans
 */
class AutoTrans extends Command
{
    protected $signature = 'make:auto-trans {source_lang_file} {views_root_dir?}';

    protected $name = "make:auto-trans";

    protected $description = "Generate language file and replace keys.";

    public function fire()
    {
        $langFilename = $this->argument('source_lang_file');

        $blade = file_get_contents('resources/views/welcome.blade.php');

        $lang = self::getLang($blade);

        self::sortByKeyLenDesc($lang);

        if (empty($lang))
            return 'No hard-strings found!';

        //while (file_exists("./resources/lang/en/" . ($langFilename = 'message' . rand(1000, 9999)) . '.php'));

        $newBlade = self::replaceKeys($lang, $blade, $langFilename);

        file_put_contents("resources/views/welcome2.blade.php", $newBlade);

        $existingLang = self::getExistingLang($langFilename);
        $finalLang = array_merge($existingLang, $lang);
        self::saveLangFile($langFilename, $finalLang);
    }

    private static function sortByKeyLenDesc(&$lang)
    {
        $keys = array_map('strlen', array_keys($lang));
        array_multisort($keys, SORT_DESC, $lang);
    }

    private static function replaceKeys($lang, $blade, $langFilename)
    {
        //return str_replace(array_values($lang), $transTaggedKeys, $blade);
        $result = $blade;
        array_map(function ($item) use ($langFilename, $blade, $lang, &$result) {
            $key = str_replace('{key}', $item, "{{ trans('$langFilename.{key}') }}");
            while (($pos = strpos(self::extractHtml($result), $lang[$item])) !== false){
            $result = substr_replace($result, $key, $pos, strlen($lang[$item]));
        }
        }, array_keys($lang));
        return ($result);
    }

    private static function extractHtml($html)
    {
        return preg_replace_callback(['/\{\{(.*)\}\}/', '/@\w*|(\(([^()]|(?R))*\))/', '/<[^()^<]*>/'], function ($match) {
            return str_pad('', strlen($match[0]), ' ');
        }, $html);
    }

    public static function saveLangFile($langFilename, $finalLang)
    {
        $finalLangString = "<?php\nreturn " . var_export($finalLang, true) . ';';
        file_put_contents("./resources/lang/en/$langFilename.php", $finalLangString);
    }

    public static function getLang($blade)
    {
        ////////$text = preg_replace(['/\{\{(.*)\}\}/', '/@(.*)\)/', '/\(([^()]*|\([^()]*\))*\)/', '/@(.*)[ |\n|\t]/', '/\t/'], "", $text);
        $entities = strip_tags($blade);
        $entities = preg_replace(['/\{\{(.*)\}\}/', '/@\w*|(\(([^()]|(?R))*\))/', '/\t/'], "", $entities);  // @\w*

        $entities = preg_replace(['/\r/', '/[ ]{2,}/'], "\n", $entities);
        $entities = preg_split('/\n/', $entities);
        $entities = array_map('trim', $entities);
        $entities = array_filter($entities);
        $keys = self::getKeys($entities);
        return array_combine($keys, $entities);

    }

    public static function getKeys($entities)
    {
        $keys = preg_replace('/^\W*|\W*$/', '', $entities);
        $keys = preg_replace('/[ ]/', '_', $keys);
        $keys = preg_replace('/\W/', '', $keys);
        return array_map('strtolower', $keys);
    }

    public static function getExistingLang($lang_file)
    {
        $lang = trans($lang_file);
        return $lang == $lang_file ? [] : $lang;

        /*$lang_files = File::files(resource_path() . '/lang/en');
        $trans = [];
        foreach ($lang_files as $f) {
            $filename = pathinfo($f)['filename'];
            $trans[$filename] = trans($filename);
        }
        return ($trans[$lang_file] === null ? [] : $trans[$lang_file]);*/
    }


    //TODO:: as bacheha's said: bejage preg az "mb" ha estefade konam
    //TODO:: ham dar zamane sakhte lang e khodesh ham zamane merge kardan baghablia, key haye tekrari ke value e motefavet daran replace nakone!
    //TODO:: get locale at first XX it works only for English language!!
    //DONE TODO:: get source lang file first

    //TODO:: az ina nabayad pish biad::: {{ trans('message1.room_resident') }}(s) ~> ke bude: Room Resident(s)

    //TODO:: age script o style o .. dasht chi??!! :-|

    //DONE TODO:: MOHEM:: replace lang ha dar blade ra az value haye BOZORGTAR be KUCHECKTAR replace konam ke age ye kalame ghatie kalameye dige bud daghun nashe

    //TODO:: make a BK dir of all of changing blades with same folder structure in root dir of them.
}