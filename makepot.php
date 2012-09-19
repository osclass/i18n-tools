<?php
require_once dirname( __FILE__ ) . '/not-gettexted.php';
require_once dirname( __FILE__ ) . '/pot-ext-meta.php';
require_once dirname( __FILE__ ) . '/extract/extract.php';

if ( !defined( 'STDERR' ) ) {
    define( 'STDERR', fopen( 'php://stderr', 'w' ) );
}

class MakePOT {
    var $max_header_lines = 30;

    var $projects = array(
        'generic',
        'plugin',
        'theme',
    );

    var $rules = array(
        '__' => array('string'),
        '_e' => array('string'),
        '_n' => array('singular', 'plural')
    );

    var $temp_files = array();

    var $meta = array(
        'default' => array(
            'from-code' => 'utf-8',
            'msgid-bugs-address' => 'http://osclass.org/',
            'language' => 'php',
            'add-comments' => 'translators',
            'comments' => "Copyright (C) {year} {package-name}\nThis file is distributed under the same license as the {package-name} package.",
        ),
        'generic' => array(),
        'plugin' => array(
            'description' => 'Translation of the Osclass plugin {name} {version} by {author}',
            'msgid-bugs-address' => 'http://osclass.org/',
            'copyright-holder' => '{author}',
            'package-name' => '{name}',
            'package-version' => '{version}',
        ),
        'theme' => array(
            'description' => 'Translation of the Osclass theme {name} {version} by {author}',
            'msgid-bugs-address' => 'http://osclass.org/',
            'copyright-holder' => '{author}',
            'package-name' => '{name}',
            'package-version' => '{version}',
            'comments' => 'Copyright (C) {year} {author}\nThis file is distributed under the same license as the {package-name} package.',
        )
    );

    function __construct($deprecated = true) {
        $this->extractor = new StringExtractor( $this->rules );
    }

    function __destruct() {
        foreach ( $this->temp_files as $temp_file )
            unlink( $temp_file );
    }

    function tempnam( $file ) {
        $tempnam = tempnam( sys_get_temp_dir(), $file );
        $this->temp_files[] = $tempnam;
        return $tempnam;
    }

    function realpath_missing($path) {
        return realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
    }

    function xgettext($project, $dir, $output_file, $placeholders = array(), $excludes = array(), $includes = array()) {
        $meta = array_merge( $this->meta['default'], $this->meta[$project] );
        $placeholders = array_merge( $meta, $placeholders );
        $meta['output'] = $this->realpath_missing( $output_file );
        $placeholders['year'] = date( 'Y' );
        $placeholder_keys = array_map( create_function( '$x', 'return "{".$x."}";' ), array_keys( $placeholders ) );
        $placeholder_values = array_values( $placeholders );
        foreach($meta as $key => $value) {
            $meta[$key] = str_replace($placeholder_keys, $placeholder_values, $value);
        }

        $originals = $this->extractor->extract_from_directory( $dir, $excludes, $includes );
        $pot = new PO;
        $pot->entries = $originals->entries;

        $pot->set_header( 'Project-Id-Version', $meta['package-name'].' '.$meta['package-version'] );
        $pot->set_header( 'Report-Msgid-Bugs-To', $meta['msgid-bugs-address'] );
        $pot->set_header( 'POT-Creation-Date', gmdate( 'Y-m-d H:i:s+00:00' ) );
        $pot->set_header( 'MIME-Version', '1.0' );
        $pot->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
        $pot->set_header( 'Content-Transfer-Encoding', '8bit' );
        $pot->set_header( 'PO-Revision-Date', date( 'Y-m-d h:i+O') );
        $pot->set_header( 'Last-Translator', 'Translations <translations@osclass.org>' );
        $pot->set_header( 'Language-Team', 'Translations <translations@osclass.org>' );
        $pot->set_comment_before_headers( $meta['comments'] );
        $pot->export_to_file( $output_file );
        return true;
    }

    function get_first_lines($filename, $lines = 30) {
        $extf = fopen($filename, 'r');
        if (!$extf) return false;
        $first_lines = '';
        foreach(range(1, $lines) as $x) {
            $line = fgets($extf);
            if (feof($extf)) break;
            if (false === $line) {
                return false;
            }
            $first_lines .= $line;
        }
        return $first_lines;
    }

    function get_addon_header($header, &$source) {
        if (preg_match('|'.$header.':(.*)$|mi', $source, $matches))
            return trim($matches[1]);
        else
            return false;
    }

    function generic($dir, $output) {
        $output = is_null($output)? "generic.pot" : $output;
        return $this->xgettext('generic', $dir, $output, array());
    }

    function guess_plugin_slug($dir) {
        if ('trunk' == basename($dir)) {
            $slug = basename(dirname($dir));
        } elseif (in_array(basename(dirname($dir)), array('branches', 'tags'))) {
            $slug = basename(dirname(dirname($dir)));
        } else {
            $slug = basename($dir);
        }
        return $slug;
    }

    function plugin($dir, $output, $slug = null) {
        $placeholders = array();
        $main_file = $dir.'/index.php';
        $source = $this->get_first_lines($main_file, $this->max_header_lines);

        $placeholders['version'] = $this->get_addon_header('Version', $source);
        $placeholders['author'] = $this->get_addon_header('Author', $source);
        $placeholders['name'] = $this->get_addon_header('Plugin Name', $source);
        $placeholders['slug'] = $slug;

        $output = is_null($output) ? "$slug.pot" : $output;
        $res = $this->xgettext('plugin', $dir, $output, $placeholders);
        if (!$res) return false;
        $potextmeta = new PotExtMeta;
        $res = $potextmeta->append($main_file, $output);
        /* Adding non-gettexted strings can repeat some phrases */
        $output_shell = escapeshellarg($output);
        system("msguniq $output_shell -o $output_shell");
        return $res;
    }

    function theme($dir, $output, $slug = null) {
        $placeholders = array();
        // guess plugin slug
        if (is_null($slug)) {
            $slug = $this->guess_plugin_slug($dir);
        }
        $main_file = $dir.'/index.php';
        $source = $this->get_first_lines($main_file, $this->max_header_lines);

        $placeholders['version'] = $this->get_addon_header('Version', $source);
        $placeholders['author'] = $this->get_addon_header('Author', $source);
        $placeholders['name'] = $this->get_addon_header('Theme Name', $source);
        $placeholders['slug'] = $slug;

        $license = $this->get_addon_header( 'License', $source );
        if ( $license )
            $this->meta['theme']['comments'] = "Copyright (C) {year} {author}\nThis file is distributed under the {$license}.";
        else
            $this->meta['theme']['comments'] = "Copyright (C) {year} {author}\nThis file is distributed under the same license as the {package-name} package.";

        $output = is_null($output)? "$slug.pot" : $output;
        $res = $this->xgettext('theme', $dir, $output, $placeholders);
        if (! $res )
            return false;
        $potextmeta = new PotExtMeta;
        $res = $potextmeta->append( $main_file, $output );
        if ( ! $res )
            return false;
        /* Adding non-gettexted strings can repeat some phrases */
        $output_shell = escapeshellarg($output);
        system("msguniq $output_shell -o $output_shell");
        return $res;
    }
}

// run the CLI only if the file
// wasn't included
$included_files = get_included_files();
if ($included_files[0] == __FILE__) {
    $makepot = new MakePOT;
    if ((3 == count($argv) || 4 == count($argv)) && in_array($method = str_replace('-', '_', $argv[1]), get_class_methods($makepot))) {
        $res = call_user_func(array(&$makepot, $method), realpath($argv[2]), isset($argv[3])? $argv[3] : null);
        if (false === $res) {
            fwrite(STDERR, "Couldn't generate POT file!\n");
        }
    } else {
        $usage  = "Usage: php makepot.php PROJECT DIRECTORY [OUTPUT]\n\n";
        $usage .= "Generate POT file from the files in DIRECTORY [OUTPUT]\n";
        $usage .= "Available projects: ".implode(', ', $makepot->projects)."\n";
        fwrite(STDERR, $usage);
        exit(1);
    }
}
