<?php
// The script is called by crontab (for all projects) and can be called by
// project transitions (for individual projects).
// It is actually 4 scripts in one file:
//   - Cleanup Pages: Bad project detection / action & reclaims MIA pages
//   - Promote Level: If a project finished a round, it sends it to next round
//   - Complete Project: If a project has completed all rounds, it sends it to post-processing
//   - Release Projects: If there are not enough projects available to end users,
//     it will release projects waiting to be released (autorelease())
$relPath = "./../../pinc/";
include_once($relPath.'base.inc');
include_once($relPath.'stages.inc');
include_once($relPath.'project_trans.inc');
include_once($relPath.'DPage.inc');
include_once($relPath.'project_states.inc');
include_once($relPath.'Project.inc'); // project_get_auto_PPer
include_once($relPath.'job_log.inc');
include_once($relPath.'misc.inc'); // requester_is_localhost(), html_safe()
include_once('autorelease.inc');

$one_project = get_projectID_param($_GET, 'project', true);
$refresh_url = array_get($_GET, 'return_uri', 'projectmgr.php');

$start_time = time();


// The following users are authorized to run this script:
// 1) localhost (eg: run from crontab) - can operate on all projects
// 2) SA and PFs - can operates on all projects
// 3) PMs - can operate only on their own projects
if (!requester_is_localhost()) {
    require_login();

    if (!user_is_a_sitemanager() && !user_is_proj_facilitator()) {
        if ($one_project) {
            $project = new Project($one_project);
            if (!$project->can_be_managed_by_user($pguser)) {
                die('You are not authorized to invoke this script.');
            }
        } else {
            die('You are not authorized to invoke this script.');
        }
    }
}

$trace = false;

// -----------------------------------------------------------------------------

function ensure_project_blurb($project)
{
    static $project_blurb_echoed = [];

    if (!isset($project_blurb_echoed[$project->projectid])) {
        echo "\n";
        echo "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX\n";
        echo "projectid  = {$project->projectid}\n";
        echo "nameofwork = \"" . html_safe($project->nameofwork) . "\"\n";
        echo "state      = {$project->state}\n";
        echo "\n";
        $project_blurb_echoed[$project->projectid] = true;
    }
}

// -----------------------------------------------------------------------------

echo "<pre>\n";

if ($one_project) {
    $verbose = $GLOBALS['testing'];
    $condition = sprintf("projectid = '%s'", DPDatabase::escape($one_project));

    insert_job_log_entry(
        'automodify.php',
        'BEGIN',
        sprintf("running for single proj %s", $one_project)
    );
} else {
    $verbose = 1;

    $condition = "0";
    foreach ($Round_for_round_id_ as $round_id => $round) {
        $condition .= sprintf(
            "
            OR state = '%s'
            OR state = '%s'
            OR state = '%s'
            ",
            DPDatabase::escape($round->project_available_state),
            DPDatabase::escape($round->project_complete_state),
            DPDatabase::escape($round->project_bad_state)
        );
    }

    insert_job_log_entry(
        'automodify.php',
        'BEGIN',
        'running for all eligible projects'
    );
}
$sql = "
    SELECT projectid
    FROM projects
    WHERE $condition
    ORDER BY projectid
";
$allprojects = DPDatabase::query($sql);
// The "ORDER BY" ensures consistency of order.

while ([$projectid] = mysqli_fetch_row($allprojects)) {
    $project = new Project($projectid);
    $state = $project->state;
    $nameofwork = $project->nameofwork;

    if ($trace) {
        ensure_project_blurb($project);
    }

    // Decide which round the project is in
    $round = get_Round_for_project_state($state);
    if (is_null($round)) {
        echo "    automodify.php: unexpected state $state for project $projectid\n";
        continue;
    }

    //Bad Page Error Check
    {
        if (($state == $round->project_available_state) || ($state == $round->project_bad_state)) {
            if ($project->is_bad_from_pages($round)) {
                // This project's pages indicate that it's bad.
                // If it isn't marked as such, make it so.
                if ($trace) {
                    echo "project looks bad.\n";
                }
                $appropriate_state = $round->project_bad_state;
            } else {
                // Pages don't indicate that the project is bad.
                // (Although it could be bad for some other reason. Hmmm.)
                if ($trace) {
                    echo "project looks okay.\n";
                }
                $appropriate_state = $round->project_available_state;
            }

            if ($state != $appropriate_state) {
                if ($verbose) {
                    ensure_project_blurb($project);
                    echo "    Re badness, changing state to $appropriate_state\n";
                }
                if ($trace) {
                    echo "changing its state to $appropriate_state\n";
                }
                $error_msg = project_transition($projectid, $appropriate_state, PT_AUTO);
                if ($error_msg) {
                    echo "$error_msg\n";
                }
                $state = $appropriate_state;
            }
        }
    }

    if (
        ($one_project) ||
        (($state == $round->project_available_state) &&
           ($project->get_num_pages_in_state($round->page_avail_state) == 0))
    ) {

        // Reclaim MIA pages

        if ($verbose) {
            ensure_project_blurb($project);
            echo "    Reclaiming any MIA pages\n";
        }

        $n_hours_to_wait = 4;
        $max_reclaimable_time = time() - $n_hours_to_wait * 60 * 60;

        $sql = sprintf(
            "
            SELECT image
            FROM $projectid
            WHERE state IN ('%s','%s')
                AND $round->time_column_name <= %d
            ORDER BY image ASC
            ",
            $round->page_out_state,
            $round->page_temp_state,
            $max_reclaimable_time
        );
        try {
            $res = DPDatabase::query($sql);
        } catch (DBQueryError $error) {
            echo $error->getMessage() . "\n";
            echo "Skipping further processing of this project.\n";
            continue;
        }

        $n_reclaimable_pages = mysqli_num_rows($res);
        if ($verbose) {
            echo "        reclaiming $n_reclaimable_pages pages\n";
        }

        while ([$image] = mysqli_fetch_row($res)) {
            Page_reclaim($projectid, $image, $round, '[automodify.php]');
        }


        // Decide whether the project is finished its current round.
        if ($state == $round->project_available_state) {
            $num_done_pages = $project->get_num_pages_in_state($round->page_save_state);
            $num_total_pages = $project->get_num_pages($projectid);

            if ($num_done_pages != $num_total_pages) {
                if ($verbose) {
                    echo "    Only $num_done_pages of $num_total_pages pages are in '$round->page_save_state'.\n";
                }
                continue;
            }

            if ($verbose) {
                echo "    All $num_total_pages pages are in '$round->page_save_state'.\n";
            }

            if (project_has_a_hold_in_state($projectid, $state)) {
                if ($verbose) {
                    echo "    Normally, this project would now advance to {$round->project_complete_state},\n";
                    echo "    but it has a hold in $state, so it stays where it is.\n";
                }
                if ($project->is_hold_notification_required($state)) {
                    // Note that notifications are only sent for Available
                    // states, as this if() block is only reached for those.
                    if ($verbose) {
                        echo "    Sending notification about held project.\n";
                    }
                    $project->send_hold_state_notification($state);
                }
                continue;
            }

            $state = $round->project_complete_state;
            if ($verbose) {
                echo "    Advancing \"" . html_safe($nameofwork) . "\" to $state\n";
            }

            $error_msg = project_transition($projectid, $state, PT_AUTO);
            if ($error_msg) {
                echo "$error_msg\n";
                continue;
            }
        }
    }

    if ($state == $round->project_complete_state) {
        // The project is ready to exit this round.

        if ($round->round_number < MAX_NUM_PAGE_EDITING_ROUNDS) {
            // It goes to the next round.
            $next_round = get_Round_for_round_number(1 + $round->round_number);
            $new_state = $next_round->project_waiting_state;
        } elseif ($round->round_number == MAX_NUM_PAGE_EDITING_ROUNDS) {
            // It goes into post-processing.
            if (is_null(project_get_auto_PPer($projectid))) {
                $new_state = PROJ_POST_FIRST_AVAILABLE;
            } else {
                $new_state = PROJ_POST_FIRST_CHECKED_OUT;
            }
        } else {
            die("round_number is {$round->round_number}???\n");
        }

        if ($verbose) {
            ensure_project_blurb($project);
            echo "    Promoting \"" . html_safe($nameofwork) . "\" to $new_state\n";
        }

        $error_msg = project_transition($projectid, $new_state, PT_AUTO);
        if ($error_msg) {
            echo "$error_msg\n";
        }
    }
}

if ($trace) {
    echo "\n";
}

if ($verbose) {
    echo "\n";
    echo "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX\n";
    echo "\n";
}

echo "</pre>\n";

if (!$one_project) {
    insert_job_log_entry(
        'automodify.php',
        'MIDDLE',
        sprintf("pre autorelease, %d seconds so far", time() - $start_time)
    );

    autorelease();

    insert_job_log_entry(
        'automodify.php',
        'END',
        sprintf(
            "post autorelease, started at %d, took %d seconds",
            $start_time,
            time() - $start_time
        )
    );
} else {
    insert_job_log_entry(
        'automodify.php',
        'END',
        sprintf(
            "end single, started at %d, took %d seconds",
            $start_time,
            time() - $start_time
        )
    );

    echo "<META HTTP-EQUIV=\"refresh\" CONTENT=\"0 ;URL=$refresh_url\">";
}
