<?
$relPath="./../../pinc/";
include_once($relPath.'v_site.inc');
include_once($jpgraph_dir.'/src/jpgraph.php');
include_once($jpgraph_dir.'/src/jpgraph_bar.php');
include_once($jpgraph_dir.'/src/jpgraph_line.php');
include_once($relPath.'connect.inc');
include_once($code_dir.'/stats/statestats.inc');
include_once($relPath.'gettext_setup.inc');

new dbConnect();

// Create "projects Xed per day" graph for current month

$which = $_GET['which'];

switch ( $which )
{
	case 'created':
		$state_selector = "
			state NOT LIKE 'proj_new%'
		";
		$legend = _('Projects Created');
		$color = 'green';
		$title = _('Projects Created Per Day for');
		break;

	case 'proofed':
		$state_selector = "
			state LIKE 'proj_submit%'
			OR state LIKE 'proj_correct%'
			OR state LIKE 'proj_post%'
		";
		$legend = _('Projects Proofed');
		$color = 'blue';
		$title = _('Projects Proofed Per Day for');
		break;

	case 'PPd':
		$state_selector = "
			state LIKE 'proj_submit%'
			OR state LIKE 'proj_correct%'
			OR state LIKE 'proj_post_second%'
		";
		$legend = _('Projects PPd');
		$color = 'silver';
		$title = _('Projects PPd Per Day for');
		break;

	case 'posted':
		$state_selector = "
			state LIKE 'proj_submit%'
			OR state LIKE 'proj_correct%'
		";
		$legend = _('Projects Posted');
		$color = 'gold';
		$title = _('Projects Posted Per Day for');
		break;

	default:
		die("bad value for 'which'");
}

$todaysTimeStamp = time();

$day = date("d", $todaysTimeStamp);
$year  = date("Y", $todaysTimeStamp);
$month = date("m", $todaysTimeStamp);
$monthVar = _(date("F", $todaysTimeStamp));
$today = $year."-".$month."-".$day;

// number of days this month - note that unlike project_state_stats, 
// which gets a row added for each new day just after midnight,
// pagestats is prepopulated with rows for the whole month
$result = mysql_query("SELECT max(day) as maxday FROM pagestats WHERE month = '$month' AND year = '$year'");
$maxday = mysql_result($result, 0, "maxday");

//query db and put results into arrays
$result = mysql_query("SELECT sum(num_projects) as PC, day FROM project_state_stats WHERE month = '$month' AND year = '$year' 
				AND ($state_selector)
				group by day ORDER BY day");
$mynumrows = mysql_numrows($result);


if ($mynumrows) {
	$base = mysql_result($result,0 , "PC");
	$datay1[0] = $base;
} else {
	$datay1[0] = 0;
}
$datax[0] = 1;
$count = 1;


while ($count <= $maxday) {
	if ($count < $mynumrows) {
		$total = mysql_result($result, $count, "PC");
	       	$datay1[$count] = $total;
		$datay1[$count-1] = $total - $datay1[$count-1];
	} else {
		$datay1[$count-1] = 0;
		}
        $datax[$count] = $count + 1;
        $count++;
}

// Create the graph. These two calls are always required
//Last value controls how long the graph is cached for in minutes
$graph = new Graph(640,400,"auto",300);
$graph->SetScale("textint");
$graph->SetMarginColor('white'); //Set background to white
$graph->SetShadow(); //Add a drop shadow
$graph->img->SetMargin(70,30,20,100); //Adjust the margin a bit to make more room for titles left, right , top, bottom

//Create the bar plot
$bplot = new BarPlot($datay1);
$bplot->SetLegend($legend);
$bplot->SetFillColor($color);

$graph->Add($bplot); //Add the bar plot to the graph

//set X axis
$graph->xaxis->SetTickLabels($datax);
$graph->xaxis->SetLabelAngle(90);
$graph->xaxis->title->Set("");

//Set Y axis
$graph->yaxis->title->Set(_("Projects"));
$graph->yaxis->SetTitleMargin(45);

$graph->title->Set("$title $monthVar $year");
$graph->title->SetFont($jpgraph_FF,$jpgraph_FS);
$graph->yaxis->title->SetFont($jpgraph_FF,$jpgraph_FS);
$graph->xaxis->title->SetFont($jpgraph_FF,$jpgraph_FS);
$graph->legend->SetFont($jpgraph_FF,$jpgraph_FS);

$graph->legend->Pos(0.05,0.5,"right" ,"top"); //Align the legend

// Display the graph
$graph->Stroke();

?>


