<?php

namespace AutoTrans;

use File;
use Illuminate\Console\Command;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class AutoTrans
 * @package Sabloger/laravel-auto-trans
 */
class AutoTrans extends Command
{
    protected $signature = 'make:auto-trans {source_lang_file} {views_root_dir?}';

    protected $name = "make:auto-trans";

    protected $description = "Generate language file and replace keys.";

    private $lang = [];
    private $langFilename;
    private $viewsRootDir;

    private $bkDirRoot = '';
    const BK_DIR_PREFIX = 'bk-';

    public function fire()
    {
        $this->langFilename = $this->argument('source_lang_file');
        if (empty($this->langFilename)) throw new \Exception('EMPTY_SOURCE_LANG_FILE');
        $this->viewsRootDir = $this->argument('views_root_dir');
        if (empty($this->viewsRootDir)) $this->viewsRootDir = 'views';

        $this->lang = $this->getExistingLang($this->langFilename);

        $view_files = File::allFiles(resource_path() . '/' . $this->viewsRootDir);
        foreach ($view_files as $view_file) {
            //$view_file = new \Symfony\Component\Finder\SplFileInfo($view_file);
            if ($view_file->getExtension() != 'php') continue;
            if (strpos($view_file->getRelativePath(), self::BK_DIR_PREFIX) === 0) continue;

            $this->takeBackup($view_file);

            print $this->transFile($view_file->getRealPath()) . "\n";
        }

        $this->saveLangFile();
        dd($this->langFilename);
    }

    public function transFile($filePath)
    {
        $blade = file_get_contents($filePath);
        if (strpos(strtolower($blade), '<script>') !== false) return 'Script skipped: ' . $filePath;
        if (strpos(strtolower($blade), '<style>') !== false) return 'Style skipped: ' . $filePath;

        $lang = $this->getLang($blade);

        $this->lang = array_merge($this->lang, $lang);
        if (empty($this->lang)) return 'No hard-strings found! ' . $filePath;
        $this->sortByKeyLenDesc();

        $newBlade = $this->replaceKeys($blade);
        file_put_contents($filePath, $newBlade);
    }

    private function takeBackup(SplFileInfo $file)
    {
        if (empty($this->bkDirRoot))
            $this->bkDirRoot = resource_path() . '/' . $this->viewsRootDir . '/' . self::BK_DIR_PREFIX . date('Y-m-d_H:i:s');

        $dir = $this->bkDirRoot . '/' . $file->getRelativePath();
        if (!File::exists($dir))
            File::makeDirectory($dir, 0777, true);

        File::copy($file->getRealPath(), $dir . '/' . $file->getFilename());
    }

    private function sortByKeyLenDesc()
    {
        $keys = array_map('strlen', array_keys($this->lang));
        array_multisort($keys, SORT_DESC, $this->lang);
    }

    private function replaceKeys($blade)
    {
        //return str_replace(array_values($lang), $transTaggedKeys, $blade);
        $result = $blade;
        array_map(function ($item) use ($blade, &$result) {
            $key = str_replace('{key}', $item, "{{ trans('$this->langFilename.{key}') }}");
            while (($pos = strpos($this->extractHtml($result), $this->lang[$item])) !== false) {
                $result = substr_replace($result, $key, $pos, strlen($this->lang[$item]));
            }
        }, array_keys($this->lang));
        return ($result);
    }

    const REGX_HTML = '/(<.*?>)|(&.*?;)|<[^()^<]*>/';
    const REGX_LARAVEL_DIRECTIVES = '/@\w*|(\(([^()]|(?R))*\))/';
    const REGX_LARAVEL_STATEMENT = '/\{\{(.*)\}\}|\{\!\!(.*)\!\!\}/';

    private function extractHtml($html)
    {
        /*HTML(with &;): (<.*?>)|(&.*?;)*/
        return preg_replace_callback([self::REGX_LARAVEL_STATEMENT, self::REGX_LARAVEL_DIRECTIVES, self::REGX_HTML], function ($match) {
            return str_pad('', strlen($match[0]), ' ');
        }, $html);
    }

    public function saveLangFile()
    {
        $finalLangString = "<?php\nreturn " . var_export($this->lang, true) . ';';
        file_put_contents("./resources/lang/en/$this->langFilename.php", $finalLangString);
    }

    public function getLang($blade)
    {
        ////////$text = preg_replace(['/\{\{(.*)\}\}/', '/@(.*)\)/', '/\(([^()]*|\([^()]*\))*\)/', '/@(.*)[ |\n|\t]/', '/\t/'], "", $text);
        $entities = strip_tags($blade);
        $entities = preg_replace([self::REGX_LARAVEL_STATEMENT, self::REGX_LARAVEL_DIRECTIVES, '/\t/'], "", $entities);

        $entities = preg_replace(['/\r/', '/[ ]{2,}/'], "\n", $entities);
        $entities = preg_split('/\n/', $entities);
        $entities = array_map('trim', $entities);
        $entities = array_filter($entities);
        $keys = $this->getKeys($entities);
        return array_combine($keys, $entities);

    }

    public function getKeys($entities)
    {
        $keys = preg_replace('/^\W*|\W*$/', '', $entities);
        $keys = preg_replace('/[ ]/', '_', $keys);
        $keys = preg_replace('/\W/', '', $keys);
        return array_map('strtolower', $keys);
    }

    public function getExistingLang($lang_file)
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

    //DONE TODO:: {!! and {{{ blades!!!
    //TODO:: Script, style and etc. tags.
    //DONE TODO:: &nbsp; and etc.

    //TODO:: placeholder html attrs!

    //TODO:: as bacheha's said: bejage preg az "mb" ha estefade konam
    //TODO:: ham dar zamane sakhte lang e khodesh ham zamane merge kardan baghablia, key haye tekrari ke value e motefavet daran replace nakone!
    //TODO:: get locale at first XX it works only for English language!!
    //DONE TODO:: get source lang file first

    //TODO:: az ina nabayad pish biad::: {{ trans('message1.room_resident') }}(s) ~> ke bude: Room Resident(s)

    //DONE TODO:: MOHEM:: replace lang ha dar blade ra az value haye BOZORGTAR be KUCHECKTAR replace konam ke age ye kalame ghatie kalameye dige bud daghun nashe

    //TODO:: make a BK dir of all of changing blades with same folder structure in root dir of them.
}