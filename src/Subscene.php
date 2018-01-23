<?php
/**
 * Created by NYXLab.
 * User: Rifal Pramadita G
 * Date: 21/01/2018
 * Time: 03.29
 */

namespace Rifalpg\Parser;

use Symfony\Component\DomCrawler\Crawler;


class Subscene
{
    private $query = null;
    private $url_download = null;
    private $url_list = null;
    private $url_detail = null;

    private $path_download = null;
    private $path_extract = null;

    private $subtitle_list = null;

    private $base_subscene = "https://subscene.com/";

    private $extract_files = [];

    /**
     * @return array
     */
    public function getExtractFiles()
    {
        return $this->extract_files;
    }

    /**
     * @return array
     */
    public function clearExtractFiles()
    {
        return $this->extract_files = [];
    }


    /**
     * Search subtitles
     *
     * @param string $query
     * @return array
     * @throws \Exception
     */
    public function search($query = null)
    {
        if ($query != null) {
            $query = urlencode($query);
            $this->query = $query;
        } elseif ($query == null && $this->query == null) {
            throw new \Exception("Query of subtitle is null");
        }

        $url = "https://subscene.com/subtitles/title?q=$this->query";
        $datas = [];

        $html = $this->curl($url);
        $crawler = new Crawler($html);
        $titles = $crawler->filter(".title")->each(function (Crawler $node) {
            return $node;
        });

        foreach ($titles as $title) {
            $datas[] = (object)[
                'title' => preg_replace('/\s+/S', '', $title->text()),
                'link' => $this->base_subscene . $title->filter('a')->attr('href')
            ];
        }

        return array_unique($datas, SORT_REGULAR);
    }

    /**
     * Get subtitles List
     *
     * @param string $url_list
     * @param array $lang
     * @return array
     * @throws \Exception
     */
    public function getList($url_list, $langs = null)
    {
        if ($url_list != null) {
            $this->url_list = $url_list;
        } elseif ($url_list == null && $this->url_list == null) {
            throw new \Exception("You need to fill url");
        }

        $html = $this->curl($this->url_list);
        $datas = [];
        $crawler = new Crawler($html);
        $tr_elements = $crawler->filterXPath('//table/tbody/tr')->each(function (Crawler $node) {
            return $node;
        });
        foreach ($tr_elements as $i => $tr) {

            $td_elements = $tr->filter('td')->each(function (Crawler $node) {
                return $node;
            });

            if (count($td_elements) > 1) {
                foreach ($td_elements as $x => $td) {

                    switch ($x) {
                        case 0:
                            $link = $this->base_subscene . $td->filter('a')->attr('href');
                            $n = $td->filter('span')->each(function (Crawler $node) {
                                return $node->text();
                            });
                            $lang = preg_replace('/\s+/S', '', $n[0]);
                            $name = preg_replace('/\s+/S', '', $n[1]);
                            break;
                        case 3:
                            $owner = preg_replace('/\s+/S', '', $td->text());
                            break;
                        default:
                            break;
                    }
                }

                if ($langs == null || in_array(strtolower($lang), array_map('strtolower', $langs))) {
                    $datas[] = (object)[
                        'name' => $name,
                        'lang' => $lang,
                        'link' => $link,
                        'owner' => $owner

                    ];

                }

            }
        }

        $this->subtitle_list = $datas;
        return $datas;
    }

    /**
     * Get Download Link
     *
     * @param string $url_detail
     * @return string
     * @throws \Exception
     */
    public function getDownloadLink($url_detail)
    {
        if ($url_detail != null) {
            $this->url_detail = $url_detail;
        } elseif ($url_detail == null && $this->url_detail == null) {
            throw new \Exception("You need to fill url");
        }
        $html = $this->curl($this->url_detail);
        $crawler = new Crawler($html);
        return $this->base_subscene . $crawler->filter('.download a')->attr('href');
    }

    /**
     * Get Download Links
     *
     * @return array
     */
    public function getDownloadLinks()
    {
        $download_links = [];
        if (count($this->subtitle_list)) {
            foreach ($this->subtitle_list as $subtitle) {
                $download_links[] = $this->getDownloadLink($subtitle->link);
            }
        }
        return $download_links;
    }

    /**
     * Download subtitle file (zip)
     *
     * @param string $url_download
     * @param string $path
     * @return string filename
     * @throws \Exception
     */

    public function download($url_download, $path)
    {
        if ($url_download != null) {
            $this->url_download = $url_download;
        } elseif ($url_download == null && $this->url_download == null) {
            throw new \Exception("You need to fill url");
        }

        if (!file_exists($path))
            mkdir($path, 0777, true);

        $content = get_headers($this->url_download, 1);
        $content = array_change_key_case($content, CASE_LOWER);
        $tmp_name = explode('=', $content['content-disposition']);
        if (!$tmp_name[1]) throw new \Exception("We can't get file name. Please try again");
        $fn = trim($tmp_name[1], '";\'');
        $file = file_get_contents($this->url_download);
        file_put_contents("$path/$fn", $file);
        return $fn;
    }

    /**
     * Download via curl (Not Recommended)
     *
     * @param string $url_download
     * @param string $path
     * @return string
     * @throws \Exception
     */
    public function curlDownload($url_download, $path)
    {
        if ($url_download != null) {
            $this->url_download = $url_download;
        } elseif ($url_download == null && $this->url_download == null) {
            throw new \Exception("You need to fill url");
        }

        if ($path != null) {
            $this->path_download = $path;
        } elseif ($path == null && $this->path_download == null) {
            throw new \Exception("You need to fill path");
        }

        return $this->curl($this->url_download, $path);
    }


    /**
     * Search Extract subtitle zip
     *
     * @param string $file
     * @param string $extract_to
     * @param boolean $delete_zip
     * @return array
     * @throws \Exception
     */
    public function extract($file, $extract_to, $delete_zip = true)
    {
        if ($extract_to != null) {
            $this->path_extract = $extract_to;
        } elseif ($extract_to == null && $this->path_extract == null) {
            throw new \Exception("You need to fill path");
        }

        if (!file_exists($extract_to))
            mkdir($extract_to);

        $datas = [];

        $ZIP_ERROR = [
            \ZipArchive::ER_EXISTS => 'File already exists.',
            \ZipArchive::ER_INCONS => 'Zip archive inconsistent.',
            \ZipArchive::ER_INVAL => 'Invalid argument.',
            \ZipArchive::ER_MEMORY => 'Malloc failure.',
            \ZipArchive::ER_NOENT => 'No such file.',
            \ZipArchive::ER_NOZIP => 'Not a zip archive.',
            \ZipArchive::ER_OPEN => "Can't open file.",
            \ZipArchive::ER_READ => 'Read error.',
            \ZipArchive::ER_SEEK => 'Seek error.',
        ];

        $zip = new \ZipArchive();

        $res = $zip->open($file);

        if ($res !== true) {
            $msg = isset($ZIP_ERROR[$res]) ? $ZIP_ERROR[$res] : 'Unknown error.';
            throw new \Exception($msg);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->statIndex($i)['name'];
            //igone .txt file
            preg_match('/\.[^\.]+$/i', $name, $ext);
            if ($ext != '.txt') {
                $datas[] = $name;
                $this->extract_files[] = $name;
            }
        }
        $zip->extractTo($this->path_extract);
        $zip->close();
        if ($delete_zip) unlink($file);
        return $datas;
    }

    private function curl($url, $download = false)
    {
        $curl = curl_init();
        $opt = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.64 Safari/537.31'

        ];

        if ($download) {
            $opt[CURLOPT_HEADER] = 1;
            $opt[CURLOPT_FOLLOWLOCATION] = true;
        }

        curl_setopt_array($curl, $opt);

        $resp = curl_exec($curl);

        if ($download) {
            $reDispo = '/^Content-Disposition: .*?filename=(?<f>[^\s]+|\x22[^\x22]+\x22)\x3B?.*$/m';
            if (preg_match($reDispo, $resp, $mDispo)) {
                $filename = trim($mDispo['f'], ' ";');
                $fp = fopen("$download/$filename", "w");
                $opt[CURLOPT_FILE] = $fp;
                $opt[CURLOPT_BINARYTRANSFER] = true;
                curl_setopt_array($curl, $opt);
                curl_exec($curl);
                curl_close($curl);
                fclose($fp);
                return $filename;
            } else {
                return false;
            }
        }

        curl_close($curl);

        return $resp;
    }

}