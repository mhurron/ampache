<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
 *
 * LICENSE: GNU General Public License, version 2 (GPLv2)
 * Copyright 2001 - 2014 Ampache.org
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License v2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 */

class Video extends database_object implements media, library_item
{
    public $id;
    public $title;
    public $played;
    public $enabled;
    public $file;
    public $size;
    public $video_codec;
    public $audio_codec;
    public $resolution_x;
    public $resolution_y;
    public $time;
    public $mime;
    public $release_date;
    public $catalog;

    public $type;
    public $tags;
    public $f_title;
    public $f_full_title;
    public $f_time;
    public $f_time_h;
    public $link;
    public $f_link;
    public $f_codec;
    public $f_resolution;
    public $f_tags;
    public $f_length;
    public $f_file;
    public $f_release_date;

    /**
     * Constructor
     * This pulls the information from the database and returns
     * a constructed object
     */
    public function __construct($id)
    {
        // Load the data from the database
        $info = $this->get_info($id, 'video');
        foreach ($info as $key=>$value) {
            $this->$key = $value;
        }

        $data = pathinfo($this->file);
        $this->type = strtolower($data['extension']);

        return true;

    } // Constructor

    public static function create_from_id($video_id)
    {
        $dtypes = self::get_derived_types();
        foreach ($dtypes as $dtype) {
            $sql = "SELECT `id` FROM `" . strtolower($dtype) . "` WHERE `id` = ?";
            $db_results = Dba::read($sql, array($video_id));
            if ($results = Dba::fetch_assoc($db_results)) {
                if ($results['id']) {
                    return new $dtype($video_id);
                }
            }
        }
        return new Video($video_id);
    }

    /**
     * build_cache
     * Build a cache based on the array of ids passed, saves lots of little queries
     */
    public static function build_cache($ids=array())
    {
        if (!is_array($ids) OR !count($ids)) { return false; }

        $idlist = '(' . implode(',',$ids) . ')';

        $sql = "SELECT * FROM `video` WHERE `video`.`id` IN $idlist";
        $db_results = Dba::read($sql);

        while ($row = Dba::fetch_assoc($db_results)) {
            parent::add_to_cache('video',$row['id'],$row);
        }

    } // build_cache

    /**
     * format
     * This formats a video object so that it is human readable
     */
    public function format()
    {
        $this->f_title = scrub_out($this->title);
        $this->f_full_title = $this->f_title;
        $this->link = AmpConfig::get('web_path') . "/video.php?action=show_video&video_id=" . $this->id;
        $this->f_link = "<a href=\"" . $this->link . "\" title=\"" . scrub_out($this->f_title) . "\"> " . scrub_out($this->f_title) . "</a>";
        $this->f_codec = $this->video_codec . ' / ' . $this->audio_codec;
        $this->f_resolution = $this->resolution_x . 'x' . $this->resolution_y;

        // Format the Time
        $min = floor($this->time/60);
        $sec = sprintf("%02d", ($this->time%60));
        $this->f_time = $min . ":" . $sec;
        $hour = sprintf("%02d", floor($min/60));
        $min_h = sprintf("%02d", ($min%60));
        $this->f_time_h = $hour . ":" . $min_h . ":" . $sec;

        // Get the top tags
        $this->tags = Tag::get_top_tags('video', $this->id);
        $this->f_tags = Tag::get_display($this->tags, true, 'video');

        $this->f_length = floor($this->time/60) . ' ' .  T_('minutes');
        $this->f_file = $this->f_title . '.' . $this->type;
        if ($this->release_date) {
            $this->f_release_date = date('Y-m-d', $this->release_date);
        }

    } // format

    public function get_keywords()
    {
        $keywords = array();
        $keywords['title'] = array('important' => true,
            'label' => T_('Title'),
            'value' => $this->f_title);

        return $keywords;
    }

    public function get_fullname()
    {
        return $this->f_title;
    }

    public function get_parent()
    {
        return null;
    }

    public function get_childrens()
    {
        return array();
    }

    public function get_medias($filter_type = null)
    {
        $medias = array();
        if (!$filter_type || $filter_type == 'video') {
            $medias[] = array(
                'object_type' => 'video',
                'object_id' => $this->id
            );
        }
        return $medias;
    }

    public function get_user_owner()
    {
        return null;
    }

    public function get_default_art_kind()
    {
        return 'preview';
    }

    /**
     * gc
     *
     * Cleans up the inherited object tables
     */
    public static function gc()
    {
        Movie::gc();
        TVShow_Episode::gc();
        TVShow_Season::gc();
        TVShow::gc();
        Personal_Video::gc();
        Clip::gc();
    }

    public function get_stream_types()
    {
        return Song::get_stream_types_for_type($this->type);
    }

    /**
     * play_url
     * This returns a "PLAY" url for the video in question here, this currently feels a little
     * like a hack, might need to adjust it in the future
     */
    public static function play_url($oid, $additional_params='', $local=false)
    {
        return Song::generic_play_url('video', $oid, $additional_params, $local);
    }

    public function get_stream_name()
    {
        return $this->title;
    }

    /**
     * get_transcode_settings
     */
    public function get_transcode_settings($target = null, $options=array())
    {
        return Song::get_transcode_settings_for_media($this->type, $target, 'video', $options);
    }

    private static function get_derived_types()
    {
        return array('TVShow_Episode', 'Movie', 'Clip', 'Personal_Video');
    }

    public static function validate_type($type)
    {
        $dtypes = self::get_derived_types();
        foreach ($dtypes as $dtype) {
            if (strtolower($type) == strtolower($dtype))
                return $type;
        }

        return 'Video';
    }

    /**
     * type_to_mime
     *
     * Returns the mime type for the specified file extension/type
     */
    public static function type_to_mime($type)
    {
        // FIXME: This should really be done the other way around.
        // Store the mime type in the database, and provide a function
        // to make it a human-friendly type.
        switch ($type) {
            case 'avi':
                return 'video/avi';
            case 'ogg':
            case 'ogv':
                return 'application/ogg';
            case 'wmv':
                return 'audio/x-ms-wmv';
            case 'mp4':
            case 'm4v':
                return 'video/mp4';
            case 'mkv':
                return 'video/x-matroska';
            case 'mkv':
                return 'video/x-matroska';
            case 'mov':
                return 'video/quicktime';
            case 'divx':
                return 'video/x-divx';
            case 'webm':
                return 'video/webm';
            case 'flv':
                return 'video/x-flv';
            case 'mpg':
            case 'mpeg':
            case 'm2ts':
            default:
                return 'video/mpeg';
        }
    }

    public static function insert($data, $gtypes = array(), $options = array())
    {
        $rezx           = intval($data['resolution_x']);
        $rezy           = intval($data['resolution_y']);
        $release_date   = intval($data['release_date']);
        $tags           = $data['genre'];

        $sql = "INSERT INTO `video` (`file`,`catalog`,`title`,`video_codec`,`audio_codec`,`resolution_x`,`resolution_y`,`size`,`time`,`mime`,`release_date`,`addition_time`) " .
            " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = array($data['file'], $data['catalog'], $data['title'], $data['video_codec'], $data['audio_codec'], $rezx, $rezy, $data['size'], $data['time'], $data['mime'], $release_date, time());
        Dba::write($sql, $params);
        $vid = Dba::insert_id();

        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    Tag::add('video', $vid, $tag, false);
                }
            }
        }

        if ($data['art'] && $options['gather_art']) {
            $art = new Art($vid, 'video');
            $art->insert_url($data['art']);
        }

        $data['id'] = $vid;
        return self::insert_video_type($data, $gtypes, $options);
    }

    private static function insert_video_type($data, $gtypes, $options = array())
    {
        if (count($gtypes) > 0) {
            $gtype = $gtypes[0];
            switch ($gtype) {
                case 'tvshow':
                    return TVShow_Episode::insert($data, $gtypes, $options);
                case 'movie':
                    return Movie::insert($data, $gtypes, $options);
                case 'clip':
                    return Clip::insert($data, $gtypes, $options);
                case 'personal_video':
                    return Personal_Video::insert($data, $gtypes, $options);
                default:
                    // Do nothing, video entry already created and no additional data for now
                    break;
            }
        }

        return $data['id'];
    }

    /**
     * update
     * This takes a key'd array of data as input and updates a video entry
     */
    public function update($data)
    {
        $f_release_date = $data['f_release_date'];
        $release_date = date_parse_from_format('Y-m-d', $f_release_date);

        $sql = "UPDATE `video` SET `title` = ?, `release_date` = ? WHERE `id` = ?";
        Dba::write($sql, array($data['title'], $release_date, $this->id));

        return $this->id;

    } // update

    public function get_release_item_art()
    {
        return array('object_type' => 'video',
            'object_id' => $this->id
        );
    }

    /*
     * generate_preview
     * Generate video preview image from a video file
     */
    public static function generate_preview($video_id, $overwrite = false)
    {
        if ($overwrite || !Art::has_db($video_id, 'video', 'preview')) {
            $artp = new Art($video_id, 'video', 'preview');
            $video = new Video($video_id);
            $image = Stream::get_image_preview($video);
            $artp->insert($image, 'image/png');
        }
    }

    /**
     * get_random
     *
     * This returns a number of random videos.
     */
    public static function get_random($count = 1)
    {
        $results = array();

        if (!$count) {
            $count = 1;
        }

        $sql = "SELECT DISTINCT(`video`.`id`) FROM `video` ";
        $where = "WHERE `video`.`enabled` = '1' ";
        if (AmpConfig::get('catalog_disable')) {
            $sql .= "LEFT JOIN `catalog` ON `catalog`.`id` = `video`.`catalog` ";
            $where .= "AND `catalog`.`enabled` = '1' ";
        }

        $sql .= $where;
        $sql .= "ORDER BY RAND() LIMIT " . intval($count);
        $db_results = Dba::read($sql);

        while ($row = Dba::fetch_assoc($db_results)) {
            $results[] = $row['id'];
        }

        return $results;
    }

    /**
     * set_played
     * this checks to see if the current object has been played
     * if not then it sets it to played. In any case it updates stats.
     */
    public function set_played($user, $agent)
    {
        Stats::insert('video', $this->id, $user, $agent);

        if ($this->played) {
            return true;
        }

        /* If it hasn't been played, set it! */
        Video::update_played('1', $this->id);

        return true;

    } // set_played

    /**
     * get_subtitles
     * Get existing subtitles list for this video
     */
    public function get_subtitles()
    {
        $subtitles = array();
        $pinfo = pathinfo($this->file);
        $filter = $pinfo['dirname'] . DIRECTORY_SEPARATOR . $pinfo['filename'] . '*.srt';

        foreach (glob($filter) as $srt) {
            $psrt = explode('.', $srt);
            $lang_code = '__';
            $lang_name = T_("Unknown");
            if (count($psrt) >= 2) {
                $lang_code = $psrt[count($psrt) - 2];
                if (strlen($lang_code) == 2) {
                    $lang_name = $this->get_language_name($lang_code);
                }
            }
            $subtitles[] = array(
                'file' => $pinfo['dirname'] . DIRECTORY_SEPARATOR . $srt,
                'lang_code' => $lang_code,
                'lang_name' => $lang_name
            );
        }

        return $subtitles;
    }

    protected function get_language_name($code)
    {
        $languageCodes = array(
         "aa" => T_("Afar"),
         "ab" => T_("Abkhazian"),
         "ae" => T_("Avestan"),
         "af" => T_("Afrikaans"),
         "ak" => T_("Akan"),
         "am" => T_("Amharic"),
         "an" => T_("Aragonese"),
         "ar" => T_("Arabic"),
         "as" => T_("Assamese"),
         "av" => T_("Avaric"),
         "ay" => T_("Aymara"),
         "az" => T_("Azerbaijani"),
         "ba" => T_("Bashkir"),
         "be" => T_("Belarusian"),
         "bg" => T_("Bulgarian"),
         "bh" => T_("Bihari"),
         "bi" => T_("Bislama"),
         "bm" => T_("Bambara"),
         "bn" => T_("Bengali"),
         "bo" => T_("Tibetan"),
         "br" => T_("Breton"),
         "bs" => T_("Bosnian"),
         "ca" => T_("Catalan"),
         "ce" => T_("Chechen"),
         "ch" => T_("Chamorro"),
         "co" => T_("Corsican"),
         "cr" => T_("Cree"),
         "cs" => T_("Czech"),
         "cu" => T_("Church Slavic"),
         "cv" => T_("Chuvash"),
         "cy" => T_("Welsh"),
         "da" => T_("Danish"),
         "de" => T_("German"),
         "dv" => T_("Divehi"),
         "dz" => T_("Dzongkha"),
         "ee" => T_("Ewe"),
         "el" => T_("Greek"),
         "en" => T_("English"),
         "eo" => T_("Esperanto"),
         "es" => T_("Spanish"),
         "et" => T_("Estonian"),
         "eu" => T_("Basque"),
         "fa" => T_("Persian"),
         "ff" => T_("Fulah"),
         "fi" => T_("Finnish"),
         "fj" => T_("Fijian"),
         "fo" => T_("Faroese"),
         "fr" => T_("French"),
         "fy" => T_("Western Frisian"),
         "ga" => T_("Irish"),
         "gd" => T_("Scottish Gaelic"),
         "gl" => T_("Galician"),
         "gn" => T_("Guarani"),
         "gu" => T_("Gujarati"),
         "gv" => T_("Manx"),
         "ha" => T_("Hausa"),
         "he" => T_("Hebrew"),
         "hi" => T_("Hindi"),
         "ho" => T_("Hiri Motu"),
         "hr" => T_("Croatian"),
         "ht" => T_("Haitian"),
         "hu" => T_("Hungarian"),
         "hy" => T_("Armenian"),
         "hz" => T_("Herero"),
         "ia" => T_("Interlingua (International Auxiliary Language Association)"),
         "id" => T_("Indonesian"),
         "ie" => T_("Interlingue"),
         "ig" => T_("Igbo"),
         "ii" => T_("Sichuan Yi"),
         "ik" => T_("Inupiaq"),
         "io" => T_("Ido"),
         "is" => T_("Icelandic"),
         "it" => T_("Italian"),
         "iu" => T_("Inuktitut"),
         "ja" => T_("Japanese"),
         "jv" => T_("Javanese"),
         "ka" => T_("Georgian"),
         "kg" => T_("Kongo"),
         "ki" => T_("Kikuyu"),
         "kj" => T_("Kwanyama"),
         "kk" => T_("Kazakh"),
         "kl" => T_("Kalaallisut"),
         "km" => T_("Khmer"),
         "kn" => T_("Kannada"),
         "ko" => T_("Korean"),
         "kr" => T_("Kanuri"),
         "ks" => T_("Kashmiri"),
         "ku" => T_("Kurdish"),
         "kv" => T_("Komi"),
         "kw" => T_("Cornish"),
         "ky" => T_("Kirghiz"),
         "la" => T_("Latin"),
         "lb" => T_("Luxembourgish"),
         "lg" => T_("Ganda"),
         "li" => T_("Limburgish"),
         "ln" => T_("Lingala"),
         "lo" => T_("Lao"),
         "lt" => T_("Lithuanian"),
         "lu" => T_("Luba-Katanga"),
         "lv" => T_("Latvian"),
         "mg" => T_("Malagasy"),
         "mh" => T_("Marshallese"),
         "mi" => T_("Maori"),
         "mk" => T_("Macedonian"),
         "ml" => T_("Malayalam"),
         "mn" => T_("Mongolian"),
         "mr" => T_("Marathi"),
         "ms" => T_("Malay"),
         "mt" => T_("Maltese"),
         "my" => T_("Burmese"),
         "na" => T_("Nauru"),
         "nb" => T_("Norwegian Bokmal"),
         "nd" => T_("North Ndebele"),
         "ne" => T_("Nepali"),
         "ng" => T_("Ndonga"),
         "nl" => T_("Dutch"),
         "nn" => T_("Norwegian Nynorsk"),
         "no" => T_("Norwegian"),
         "nr" => T_("South Ndebele"),
         "nv" => T_("Navajo"),
         "ny" => T_("Chichewa"),
         "oc" => T_("Occitan"),
         "oj" => T_("Ojibwa"),
         "om" => T_("Oromo"),
         "or" => T_("Oriya"),
         "os" => T_("Ossetian"),
         "pa" => T_("Panjabi"),
         "pi" => T_("Pali"),
         "pl" => T_("Polish"),
         "ps" => T_("Pashto"),
         "pt" => T_("Portuguese"),
         "qu" => T_("Quechua"),
         "rm" => T_("Raeto-Romance"),
         "rn" => T_("Kirundi"),
         "ro" => T_("Romanian"),
         "ru" => T_("Russian"),
         "rw" => T_("Kinyarwanda"),
         "sa" => T_("Sanskrit"),
         "sc" => T_("Sardinian"),
         "sd" => T_("Sindhi"),
         "se" => T_("Northern Sami"),
         "sg" => T_("Sango"),
         "si" => T_("Sinhala"),
         "sk" => T_("Slovak"),
         "sl" => T_("Slovenian"),
         "sm" => T_("Samoan"),
         "sn" => T_("Shona"),
         "so" => T_("Somali"),
         "sq" => T_("Albanian"),
         "sr" => T_("Serbian"),
         "ss" => T_("Swati"),
         "st" => T_("Southern Sotho"),
         "su" => T_("Sundanese"),
         "sv" => T_("Swedish"),
         "sw" => T_("Swahili"),
         "ta" => T_("Tamil"),
         "te" => T_("Telugu"),
         "tg" => T_("Tajik"),
         "th" => T_("Thai"),
         "ti" => T_("Tigrinya"),
         "tk" => T_("Turkmen"),
         "tl" => T_("Tagalog"),
         "tn" => T_("Tswana"),
         "to" => T_("Tonga"),
         "tr" => T_("Turkish"),
         "ts" => T_("Tsonga"),
         "tt" => T_("Tatar"),
         "tw" => T_("Twi"),
         "ty" => T_("Tahitian"),
         "ug" => T_("Uighur"),
         "uk" => T_("Ukrainian"),
         "ur" => T_("Urdu"),
         "uz" => T_("Uzbek"),
         "ve" => T_("Venda"),
         "vi" => T_("Vietnamese"),
         "vo" => T_("Volapuk"),
         "wa" => T_("Walloon"),
         "wo" => T_("Wolof"),
         "xh" => T_("Xhosa"),
         "yi" => T_("Yiddish"),
         "yo" => T_("Yoruba"),
         "za" => T_("Zhuang"),
         "zh" => T_("Chinese"),
         "zu" => T_("Zulu")
        );

        return $languageCodes[$code];
    }

    public function get_subtitle_file($lang_code)
    {
        $subtitle = '';
        if ($lang_code == '__' || $this->get_language_name($lang_code)) {
            $pinfo = pathinfo($this->file);
            $subtitle = $pinfo['dirname'] . DIRECTORY_SEPARATOR . $pinfo['filename'];
            if ($lang_code != '__') {
                $subtitle .= '.' . $lang_code;
            }
            $subtitle .= '.srt';
        }

        return $subtitle;
    }

    /**
     * update_played
     * sets the played flag
     */
    public static function update_played($new_played,$song_id)
    {
        self::_update_item('played',$new_played,$song_id,'25');

    } // update_played

    /**
     * _update_item
     * This is a private function that should only be called from within the video class.
     * It takes a field, value video id and level. first and foremost it checks the level
     * against $GLOBALS['user'] to make sure they are allowed to update this record
     * it then updates it and sets $this->{$field} to the new value
     */
    private static function _update_item($field, $value, $song_id, $level)
    {
        /* Check them Rights! */
        if (!Access::check('interface',$level)) { return false; }

        /* Can't update to blank */
        if (!strlen(trim($value))) { return false; }

        $sql = "UPDATE `video` SET `$field` = ? WHERE `id` = ?";
        Dba::write($sql, array($value, $song_id));

        return true;

    } // _update_item

} // end Video class
