<?php

    namespace Coco\simplePageDownloader;

class Utils
{
    public static function sanitizeFileName($folderName): string
    {
        $folderName = preg_replace('#[\s]+#', '_', $folderName);

        $t = explode(DIRECTORY_SEPARATOR, $folderName);

        $result = array_map(function ($section) {

            $specialChars = [
                ' ',
                '>',
                '<',
                '?',
                ':',
                '*',
                '|',
                '"',
                '\'',
            ];

            $h = (DIRECTORY_SEPARATOR == '\\') ? "/" : "\\";

            $specialChars[] = $h;

            return str_replace($specialChars, '_', $section);
        }, $t);

        return implode(DIRECTORY_SEPARATOR, $result);
    }

    public static function strToDBC($str): string
    {
        $arr = [
            '０' => '0',
            '１' => '1',
            '２' => '2',
            '３' => '3',
            '４' => '4',
            '５' => '5',
            '６' => '6',
            '７' => '7',
            '８' => '8',
            '９' => '9',
            'Ａ' => 'A',
            'Ｂ' => 'B',
            'Ｃ' => 'C',
            'Ｄ' => 'D',
            'Ｅ' => 'E',
            'Ｆ' => 'F',
            'Ｇ' => 'G',
            'Ｈ' => 'H',
            'Ｉ' => 'I',
            'Ｊ' => 'J',
            'Ｋ' => 'K',
            'Ｌ' => 'L',
            'Ｍ' => 'M',
            'Ｎ' => 'N',
            'Ｏ' => 'O',
            'Ｐ' => 'P',
            'Ｑ' => 'Q',
            'Ｒ' => 'R',
            'Ｓ' => 'S',
            'Ｔ' => 'T',
            'Ｕ' => 'U',
            'Ｖ' => 'V',
            'Ｗ' => 'W',
            'Ｘ' => 'X',
            'Ｙ' => 'Y',
            'Ｚ' => 'Z',
            'ａ' => 'a',
            'ｂ' => 'b',
            'ｃ' => 'c',
            'ｄ' => 'd',
            'ｅ' => 'e',
            'ｆ' => 'f',
            'ｇ' => 'g',
            'ｈ' => 'h',
            'ｉ' => 'i',
            'ｊ' => 'j',
            'ｋ' => 'k',
            'ｌ' => 'l',
            'ｍ' => 'm',
            'ｎ' => 'n',
            'ｏ' => 'o',
            'ｐ' => 'p',
            'ｑ' => 'q',
            'ｒ' => 'r',
            'ｓ' => 's',
            'ｔ' => 't',
            'ｕ' => 'u',
            'ｖ' => 'v',
            'ｗ' => 'w',
            'ｘ' => 'x',
            'ｙ' => 'y',
            'ｚ' => 'z',
            '・' => '.',
            '＝' => '=',
            '—' => '-',
            '－' => '-',
            '￣' => '-',
            '（' => '(',
            '）' => ')',
            '：' => ':',
            '；' => ';',
            '？' => '?',
            '〃' => '"',
            '，' => ',',
            '〔' => '[',
            '〕' => ']',
            '［' => '[',
            '］' => ']',
            '｛' => '{',
            '｝' => '}',
            '％' => '%',
            '＋' => '+',
            '｜' => '|',
            '．' => '.',
            '！' => '!',
            '＜' => '<',
            '＞' => '>',
            '‹' => '<',
            '›' => '>',
            '〈' => '<',
            '〉' => '>',
            '﹝' => '[',
            '﹞' => ']',
            '「' => '[',
            '」' => ']',
            '«' => '《',
            '»' => '》',

            //'〝' => '"' ,
            //'〞' => '"' ,
            //'`' => "'" ,
            //'´' => "'" ,
            //'ˋ' => "'" ,
            //'ˊ' => "'" ,
            //'＂' => '"' ,

            //'“' => '"' ,
            //'”' => '"' ,

            //'‘' => "'" ,
            //'’' => "'" ,

            //'《' => '<' ,
            //'》' => '>' ,
            //'～' => '-' ,
            //'、' => ',' ,
            //'…' => '-' ,
            //'‖' => '|' ,
            //'〗' => ']' ,
            //'〖' => '[' ,
            //'】' => ']' ,
            //'【' => '[' ,

            //            ' ' => ' ',
            //            '…' => ' ',
            //            '　' => ' ',
            //            '􀆰' => ' ',
            //            ' ' => ' ',
            //            '□' => ' ',
            //            '' => ' ',
            //            "\uE011" => "-",
            //            "犲狋犪犾" => "et al",
            "ꎬ" => ",",
        ];

        $str = strtr($str, $arr);

        return $str;
    }
}
