<?php
require_once "constants.php";
require_once "ids.php"; // Contains the identifier + Password to connect to RTone web API
require_once "common.php";
require_once "helpers.php";


$db = connect_to_db();

// TODO - Or get_meter_list_origin()?
$meters = get_meter_list($db, 36);

// Create CSV output
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=prod.csv");
$output_csv = fopen("php://output", 'w');
fputcsv($output_csv, array("Compteur", "Date d'installation", "Année", "Prod1 (kWh)", "Prod2 (kWh)", "Prod3 (kWh)", "Total (kWh)"));


foreach ($meters as $meter) {
    $installation_date = (new DateTime('@'.$meter["fisrtts"]))->setTime(0, 0, 0);
    $anniversary_date = clone $installation_date;

    while ($anniversary_date < new DateTime()) {
        $csv_row = [];
        $period_1 = new time_period();
        $period_2 = new time_period();
        $period_3 = new time_period();

        $csv_row[] = $meter["name"];
        $csv_row[] = $installation_date->format("d/m/Y");
        $csv_row[] = $anniversary_date->format("Y");

        $period_1->date_start->setDate($anniversary_date->format('Y'), 1, 1);
        if ($anniversary_date->format('m') > 6) {
            // First period is 6 months
            $period_1->date_end->setDate($anniversary_date->format('Y'), 7, 1);
            $period_2->date_start = (clone $period_1->date_end);
            $period_2->date_end = (clone $anniversary_date)->modify("+1 day");
            $period_3->date_start = (clone $period_2->date_end);
            $period_3->date_end = (clone $period_3->date_start)->setDate($period_3->date_start->format('Y')+1, 1, 1);
        } else {
            // Second period is 6 months
            $period_1->date_end = (clone $anniversary_date)->modify("+1 day");
            $period_2->date_start = (clone $period_1->date_end);
            $period_2->date_end = (clone $period_2->date_start)->modify("+6 months")->modify("+1 day");
            $period_3->date_start = (clone $period_2->date_end);
            $period_3->date_end = (clone $period_3->date_start)->setDate($period_3->date_start->format('Y')+1, 1, 1);
        }

        foreach ([$period_1, $period_2, $period_3] as $period) {
            switch ($meter["family"]) {
                case "rbee":
                    $sum = get_rbee_prod($db, $meter["serial"], $period->date_start, $period->date_end);
                    $csv_row[] = $sum/1000;
                    break;
                case "tic":
                    break;
                case "ticpmepmi":
                    break;
                default:
                    throw new Exception("Unknown meter family: ".$meter["family"]);
            }
        }
        // Compute total and write line to the CSV file
        $csv_row[] = $csv_row[3] + $csv_row[4] + $csv_row[5];
        fputcsv($output_csv, $csv_row);

        $anniversary_date->modify("+1 year");
    }
}

?>