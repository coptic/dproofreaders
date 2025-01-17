<?php

// Searching for book records in an external catalog
// via Z39.50 protocol (implemented by yaz library).

$relPath = '../../pinc/';
include_once($relPath.'base.inc');
include_once($relPath.'theme.inc');
include_once($relPath.'misc.inc'); // attr_safe()
include_once($relPath.'MARCRecord.inc');

require_login();

$action = @$_REQUEST['action'];

if ($action == 'show_query_form') {
    show_query_form();
} elseif ($action == "do_search_and_show_hits") {
    do_search_and_show_hits();
} else {
    die("unrecognized value for 'action' parameter: '$action'");
}

// XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX

function show_query_form()
{
    $title = _("Create a Project");
    output_header($title);

    if (!function_exists('yaz_connect')) {
        echo "<p class='error'>";
        echo _("PHP is not compiled with YAZ support.  Please do so and try again.");
        echo "</p>";
        echo "<p>";
        echo sprintf(
            _("Until you do so, click <a href='%s'>here</a> for creating a new project."),
            'editproject.php?action=createnew'
        );
        echo "</p>";
        echo "<p>";
        echo sprintf(
            _("If you believe you should be seeing the Create Project page please contact a <a href='%s'>Site Administrator</a>"),
            "mailto:".$GLOBALS['site_manager_email_addr']
        );
        echo "</p>";
    } else {
        echo "<h1>$title</h1>";

        echo "<p>";
        echo _("Please put in as much information as possible to search for your project.  The more information the better but if not accurate enough may rule out results.");
        echo "</p>";

        echo "<form method='post' action='external_catalog_search.php'>\n";
        echo "<input type='hidden' name='action' value='do_search_and_show_hits'>\n";
        echo "<table class='basic'>";

        foreach (
            [
                'title' => _('Title'),
                'author' => _('Author'),
                'publisher' => _('Publisher'),
                'pubdate' => _('Publication Year (eg: 1912)'),
                'isbn' => _('ISBN'),
                'issn' => _('ISSN'),
                'lccn' => _('LCCN'),
            ]
            as $field_name => $field_label
        ) {
            echo "<tr>";
            echo   "<th class='label'>$field_label</th>";
            echo   "<td>";
            echo     "<input type='text' size='30' name='$field_name' maxlength='255'>";
            echo   "</td>";
            echo "</tr>\n";
        }

        echo "<tr>";
        echo   "<th colspan='2'>";
        echo     "<input type='submit' value='", attr_safe(_('Search')), "'>";
        echo   "</th>";
        echo "</tr>\n";

        echo "</table>";
        echo "</form>\n";
    }
}

// XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX

function do_search_and_show_hits()
{
    output_header("Search Results");
    echo "<br>";
    if (empty($_GET['start'])) {
        $start = 1;
    } else {
        $start = $_GET['start'];
    }
    if (!empty($_GET['fq'])) {
        $fullquery = unserialize(base64_decode($_GET['fq']));
    } else {
        $fullquery = query_format();
    }

    global $external_catalog_locator;
    // We request UTF-8 character set, but according to the docs (and our testing)
    // most servers ignore this and return ISO-8859-1 anyway. The strings get
    // converted to UTF-8 via MARCRecord::__get() instead.
    $id = yaz_connect($external_catalog_locator, ["charset" => "UTF-8"]);
    yaz_syntax($id, "usmarc");
    yaz_element($id, "F");
    yaz_search($id, "rpn", trim($fullquery));
    $extra_options = ["timeout" => 60];
    yaz_wait($extra_options);
    $errorMsg = yaz_error($id);

    if (!empty($errorMsg)) {
        echo "<p class='error'>";
        echo _("The following error has occurred:");
        echo " $errorMsg";
        echo "</p>";
        echo "<p>";
        $url = "editproject.php?action=createnew";
        echo sprintf(
            _("Please try again. If the problem recurs, please create your project manually by following this <a href='%s'>link</a>."),
            $url
        );
        echo "</p>";
        exit();
    }

    if (yaz_hits($id) == 0) {
        echo "<p class='warning'>";
        echo  _("There were no results returned.");
        echo "</p>";
        echo "<p>";
        echo sprintf(_("Please search again or click '%s' to create the project manually."), _("No Matches"));
        echo "</p>";
    } else {
        echo "<p>";
        echo sprintf(
            _("%d results returned. Note that some non-book results may not be displayed."),
            yaz_hits($id)
        );
        echo "</p>";
        echo "<p>";
        echo _("Please pick a result from below:");
        echo "</p>";
    }

    echo "<form method='post' action='editproject.php'>";
    echo "<input type='hidden' name='action' value='create_from_marc_record'>";
    echo "<table style='width: 100%; border: 0;'>";

    // -----------------------------------------------------

    $hits_per_page = 20; // Perhaps later this can be a PM preference or an option on the form.
    $i = 1;
    while (($start <= yaz_hits($id) && $i <= $hits_per_page)) {
        $rec = yaz_record($id, $start, "array");

        // if $rec isn't an array, then yaz_record() failed and we should
        // skip this record
        if (!is_array($rec)) {
            $start++;
            continue;
        }

        //if it's not a book don't display it.  we might want to uncomment in the future if there are too many records being returned - if (substr(yaz_record($id, $start, "raw"), 6, 1) != "a") { $start++; continue; }
        $marc_record = new MARCRecord();
        $marc_record->load_yaz_array($rec);

        if ($i % 2 == 1) {
            echo "<tr>";
        }

        echo "<td class='center-align top-align' style='width: 5%;'>";
        echo "<input type='radio' name='rec' value='".base64_encode(serialize($rec))."'>";
        echo "</td>";
        echo "<td class='left-align top-align' style='width: 45%;'>";
        echo "<table class='basic' style='width: 100%;'>";

        foreach ([
            ['label' => _("Title"),     'value' => $marc_record->title],
            ['label' => _("Author"),    'value' => $marc_record->author],
            ['label' => _("Publisher"), 'value' => $marc_record->publisher],
            ['label' => _("Language"),  'value' => $marc_record->language],
            ['label' => _("LCCN"),      'value' => $marc_record->lccn],
            ['label' => _("ISBN"),      'value' => $marc_record->isbn],
        ]
            as $couple
        ) {
            $label = $couple['label'];
            $value = $couple['value'];
            echo "<tr>";
            echo   "<th class='left-align top-align' style='width: 20%;'>$label:</th>";
            echo   "<td class='left-align top-align'>$value</td>";
            echo "</tr>\n";
        }

        echo "</table><p></td>";

        if ($i % 2 != 1) {
            echo "</tr>\n";
        }

        $i++;
        $start++;
    }
    if ($i % 2 != 1) {
        echo "</tr>\n";
    }

    // -----------------------------------------------------

    $encoded_fullquery = base64_encode(serialize($fullquery));
    echo "<tr>";
    echo "<td class='left-align top-align' style='width: 50%;' colspan='2'>";
    if (isset($_GET['start']) && ($_GET['start'] - $hits_per_page) > 0) {
        $url = "external_catalog_search.php?action=do_search_and_show_hits&start=".($_GET['start'] - $hits_per_page)."&fq=$encoded_fullquery";
        echo "<a href='$url'>Previous</a>";
    } else {
        echo "&nbsp;";
    }
    echo "</td>";
    echo "<td class='right-align top-align' style='width: 50%;' colspan='2'>";
    if (($start + $hits_per_page) <= yaz_hits($id)) {
        $url = "external_catalog_search.php?action=do_search_and_show_hits&start=$start&fq=$encoded_fullquery";
        echo "<a href='$url'>Next</a>";
    } else {
        echo "&nbsp;";
    }
    echo "</td>";
    echo "</tr>\n";

    // -----------------------------------------------------

    echo "</table><p class='center-align'>";
    if (yaz_hits($id) != 0) {
        echo "<input type='submit' value='", attr_safe(_("Create the Project")), "'>&nbsp;";
    }

    $label = attr_safe(_('Search Again'));
    $url = "external_catalog_search.php?action=show_query_form";
    echo "<input type='button' value='$label' onclick='javascript:location.href=\"$url\";'>";
    echo "&nbsp;";

    $label = attr_safe(_('No Matches'));
    $url = "editproject.php?action=createnew";
    echo "<input type='button' value='$label' onclick='javascript:location.href=\"$url\";'>";
    echo "&nbsp;";

    $label = attr_safe(_('Quit'));
    $url = "projectmgr.php";
    echo "<input type='button' value='$label' onclick='javascript:location.href=\"$url\";'>";

    echo "</p>";
    echo "</form>";
    yaz_close($id);
}

// XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX

function query_format()
{
    $attr_set = 0;
    $fullquery = "";

    if ($_REQUEST['title']) {
        $fullquery = $fullquery.' @attr 1=4 "'.$_REQUEST['title'].'"';
        $attr_set++;
    }
    if ($_REQUEST['author']) {
        $author = $_REQUEST['author'];
        if (stristr($_REQUEST['author'], ",")) {
            $author = $_REQUEST['author'];
        } else {
            if (stristr($_REQUEST['author'], " ")) {
                $author = substr($_REQUEST['author'], strrpos($_REQUEST['author'], " "))
                    . ", "
                    . substr($_REQUEST['author'], 0, strrpos($_REQUEST['author'], " "));
            }
        }
        $fullquery = $fullquery.' @attr 1=1003 "'.trim($author).'"';
        $attr_set++;
    }
    if ($_REQUEST['isbn']) {
        $fullquery = $fullquery.' @attr 2=3 @attr 1=7 '.str_replace("-", "", $_REQUEST['isbn']).'';
        $attr_set++;
    }
    if ($_REQUEST['issn']) {
        $fullquery = $fullquery.' @attr 2=3 @attr 1=8 '.$_REQUEST['issn'].'';
        $attr_set++;
    }
    if ($_REQUEST['lccn']) {
        $fullquery = $fullquery.' @attr 2=3 @attr 1=9 '.$_REQUEST['lccn'].'';
        $attr_set++;
    }
    if ($_REQUEST['pubdate']) {
        $fullquery = $fullquery.' @attr 2=3 @attr 1=31 '.$_REQUEST['pubdate'].'';
        $attr_set++;
    }
    if ($_REQUEST['publisher']) {
        $fullquery = $fullquery.' @attr 1=1018 "'.$_REQUEST['publisher'].'"';
        $attr_set++;
    }
    for ($i = 1; $i <= ($attr_set - 1); $i++) {
        $fullquery = "@and ".$fullquery;
    }
    return $fullquery;
}
