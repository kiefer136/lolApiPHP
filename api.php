<?php
// Set up connection to local database

function establishDBConnection() {
    $connect = mysqli_connect("localhost:3306", "root", "", "lolchallenges");

    return $connect;
}

// Make request to RIOTAPI with url
function CallAPI($url) {
    $apiKey = "RGAPI-9f02ea6d-2857-421c-a514-62839886ef88";
    $cURLConnection = curl_init();

    echo "Requesting URL: ".$url;

    curl_setopt($cURLConnection, CURLOPT_URL, $url);
    curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cURLConnection, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($cURLConnection, CURLOPT_HTTPHEADER, array(
        "X-Riot-Token: $apiKey",
    ));

    $response = curl_exec($cURLConnection);
    $err = curl_error($cURLConnection);
    curl_close($cURLConnection);

    if ($err) {
        var_dump($err);
        return "error";
    } else {
        $jsonResponse = json_decode($response);
        return $jsonResponse;
    }
}

// Looks up a players account by name & tag line and saves it to the accounts table
function lookupAndSaveAccount($database, $name, $tagLine) {
    $baseUrl = "https://americas.api.riotgames.com/riot/";
    $route = "account/v1/accounts/by-riot-id/";
    
    $requestAccountUrl = $baseUrl.$route.$name.'/'.$tagLine;
    
    $api_result = CallAPI($requestAccountUrl);
    if (is_object($api_result)) {
        var_dump($api_result);
    
        if ($api_result->puuid) {
            $puuid = $api_result->puuid;
            $name = $api_result->gameName;
            $tagLine = $api_result->tagLine;
            echo $puuid;
    
            $sqlInsert = "INSERT IGNORE INTO accounts (puuid, name, tagLine) VALUES ('$puuid', '$name', '$tagLine')";
    
            $database->query($sqlInsert);
        }
    }
}
// Look up match history based on timeline
function queryMatchHistory($database, $puuid) {
    $days = 1;
    $endDay = ($days + 1);

    while($days < 30) {
        var_dump($days);
        $days++;
        $currentDate = strtotime("now -$days days");
        $yesterday = strtotime("-$endDay days", $currentDate);
    
        usleep(250000);
        $baseUrl = "https://americas.api.riotgames.com/";
        $route = "lol/match/v5/matches/by-puuid/$puuid/ids?startTime=$yesterday&endTime=$currentDate&count=20";
    
        $requestAccountUrl = $baseUrl.$route;
    
        $api_result = CallAPI($requestAccountUrl);
        
        if (is_array($api_result)) {
            foreach ($api_result as $match) {
                $id = $match;
                $sqlInsert = "INSERT IGNORE INTO matches (puuid, matchID) VALUES ('$puuid', '$id')";
                echo "inserting $id ....";
                $database->query($sqlInsert);
            }
        } else {
            echo "Is not Array";
        }

        if ($days > 100) {
            exit;
        }
    }

}

// Retrieve match info from match id
function queryMatchsHistoryInfo($database, $matchIDs, $summonerPUUID) {

    $baseUrl = "https://americas.api.riotgames.com/";
    
    foreach($matchIDs as $matchID) {
        $route = "lol/match/v5/matches/$matchID";
        $requestAccountUrl = $baseUrl.$route;

        $api_result = CallAPI($requestAccountUrl);
        echo 'sleep';
        usleep(250000);
        if (!empty(($api_result->info))) {
            $obj = array_column($api_result->info->participants, null, 'puuid')[$summonerPUUID] ?? false;
            $placement = $obj->placement ? $obj->placement : 0;
            $champion = $obj->championName ? $obj->championName : "null";
        
            if ($placement == 1) {
                echo $champion.' || '.$placement;
                $sqlInsert = "INSERT IGNORE INTO accountschampions (championName, puuid) VALUES ('$champion','$summonerPUUID')";
                $database->query($sqlInsert);
            }
        }
    }
}
//
function getSummonerChampionsByName($name, $database) {
    $sqlInsert = 
    "SELECT * 
    FROM accountschampions 
    INNER JOIN accounts ON accountschampions.puuid=accounts.puuid
    WHERE accounts.name = '$name'";
    $champs = $database->query($sqlInsert);

    $champss = [];
    while($row = mysqli_fetch_array($champs)) {
        $champss[] = !empty($row['championName']) ? $row['championName'] : '';
    }
    return $champss;
}

$database = establishDBConnection();
$name = "Aviator";
$tagLine = "";
//Clean name string before requesting
// $name = str_replace(' ', '', $name);

// lookupAndSaveAccount($database, $name, $tagLine);

//-------------------------------------------------------------------------------------
$result = mysqli_query($database, "SELECT puuid, name FROM accounts WHERE name = '$name' LIMIT 20");
$resultRow = $result->fetch_assoc();
if ($resultRow) {
    echo 'Puuid: '.$resultRow['puuid'].'</br>';
    echo 'Summoner Name: '.$resultRow['name'].'</br>';
}
$summonerPUUID = $resultRow['puuid'];
//
$matchResult = mysqli_query($database, "SELECT * FROM matches WHERE puuid = '$summonerPUUID' ORDER BY matchID");
$matchResultRow = $matchResult->fetch_assoc();
$matchIDs = [];
while($row = mysqli_fetch_array($matchResult)) {
    $matchIDs[] = !empty($row['matchID']) ? $row['matchID'] : '';
}
// var_dump($matchResultRow);
// $matchListId = $matchResultRow;

// var_dump($matchId);
// queryMatchHistory($database, $summonerPUUID);
// queryMatchsHistoryInfo($database, $matchIDs, $summonerPUUID);
//-------------------------------------------------------------------------------------
$champs = getSummonerChampionsByName($_POST["name"], $database);
echo 'Placed first with '.count($champs).' total champions </br>';
foreach ($champs as $champ) {
    echo $champ.'</br>';
}
?>