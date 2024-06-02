<?php

//START FUNCTIONS
//-------------------------------------------------------------------------------------
// Set up connection to local database
function establishDBConnection() {
    $connect = mysqli_connect(
        "postgres://lolchallenges_user:sVJRM5Vdc0mkV8jAomNi7wb4EHcBdd2m@dpg-cpe2jd7109ks73eom1k0-a/lolchallenges", 
        "rlolchallenges_useroot", 
        "sVJRM5Vdc0mkV8jAomNi7wb4EHcBdd2m", 
        "lolchallenges", 
        5432
    );

    return $connect;
}

// Make request to RIOTAPI with url
function CallAPI($url) {
    $apiKey = "RGAPI-101c74a1-4b7c-419c-96e0-49d1199a3cfe";
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
function queryMatchHistory($database, $puuid, $startStart, $endTime, $maxDays) {
    $days = $startStart;
    $endDay = ($endTime + 1);
    $days = 0 ? "-$days" : "$days";

    while($days < $maxDays) {
        $currentDate = strtotime("now -$days days");
        $yesterday = strtotime("-$endDay days", $currentDate);
        $days++;
        usleep(250000);
        $baseUrl = "https://americas.api.riotgames.com/";
        $route = "lol/match/v5/matches/by-puuid/$puuid/ids?startTime=$yesterday&endTime=$currentDate&count=20";
    
        $requestAccountUrl = $baseUrl.$route;
    
        $api_result = CallAPI($requestAccountUrl);
        
        if (is_array($api_result)) {
            foreach ($api_result as $match) {
                $id = $match;
                $sqlInsert = "INSERT IGNORE INTO matches (puuid, matchID) VALUES ('$puuid', '$id')";
                echo "inserting $id ....</br>";
                $database->query($sqlInsert);
            }
        } else {
            echo "Is not Array";
        }

        if ($days > 40) {
            exit;
        }
    }

}

// Retrieve match info from match id
function queryMatchsHistoryInfo($database, $matchIDs, $summonerPUUID) {
    $baseUrl = "https://americas.api.riotgames.com/";
    $count = 1;
    foreach($matchIDs as $matchID) {
        echo $matchID;
        if ($count > 400) {
            exit();
        }
        $route = "lol/match/v5/matches/$matchID";
        $requestAccountUrl = $baseUrl.$route;
        $api_result = CallAPI($requestAccountUrl);
        usleep(650000);
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
        $count++;
    }
}
// Get summoners list of champions placed first in arena by summoner name
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
//GET ACCOUNT
function getAccount($database, $name) {
    $result = mysqli_query($database, "SELECT puuid, name FROM accounts WHERE name = '$name' LIMIT 20");
    $resultRow = $result->fetch_assoc();
    if ($resultRow) {
        return ['puuid'=>$resultRow['puuid'], 'name'=>$resultRow['name']];
    }
}
// END FUNCTIONS
//-------------------------------------------------------------------------------------
//Set DB
//Clean name string before requesting
// $name = str_replace(' ', '', $name);
//-------------------------------------------------------------------------------------

// PHP AJAX
//-------------------------------------------------------------------------------------
if (isset($_POST['action'])) {
    $database = establishDBConnection();
    $name = !empty($_POST["name"]) ? $_POST["name"] : '';
    $tagLine = !empty($_POST["tagLine"]) ? $_POST["tagLine"] : '';
    if ($name != '') {
        $account = getAccount($database, $name);
        if (!empty($account['name'])) {
            $summonerPUUID = $account['puuid'];
            echo "<h4>Summoner Puuid: ".$summonerPUUID."</h4>".'</br>'."<h4>Summoner Name: ".$account['name'].'</h4></br>';
            switch ($_POST['action']) {
                case 'queryMatches':
                    return queryMatches($database, $summonerPUUID);
                    break;
                case 'getChampions':
                    return getChampions($name, $database);
                case 'queryMatchInfo':
                    return getMatchInfo($database, $summonerPUUID, $name);
                case 'setDbStructure':
                    setDbStructure($database);
            }
        } else {
            return summonerLookup($database, $name, $tagLine);
        }
    } else {
        echo "Missing Name";
    }
}

function queryMatches($database, $summonerPUUID) {
    queryMatchHistory($database, $summonerPUUID, "0", "1", 40);;
    exit;
}

function getMatchInfo($database, $summonerPUUID, $name) {
    $matchResult = mysqli_query($database, "SELECT * FROM matches WHERE puuid = '$summonerPUUID' ORDER BY matchID");
    $matchResultRow = $matchResult->fetch_assoc();
    $matchIDs = [];
    while($row = mysqli_fetch_array($matchResult)) {
        $matchIDs[] = !empty($row['matchID']) ? $row['matchID'] : '';
    }
    queryMatchsHistoryInfo($database, $matchIDs, $summonerPUUID);
    exit;
}

function getChampions($name, $database) {
    $champs = getSummonerChampionsByName($name, $database);
    echo 'Placed first with '.count($champs).' total champions </br>';
    foreach ($champs as $champ) {
        echo $champ.'</br>';
    }
}

function summonerLookup($database,$name, $tagLine) {
    if ($name === '' || $tagLine === '') {
        echo '<p>Summoner not known, or missing name / tagLine</p>';
    } else {
        lookupAndSaveAccount($database, $name, $tagLine);
    }
    exit;
}
function setDbStructure($database) {
    mysqli_query($database,  
    "DROP TABLE IF EXISTS `accounts`;
    CREATE TABLE IF NOT EXISTS `accounts` (
      `puuid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
      `name` varchar(30) DEFAULT NULL,
      `tagLine` varchar(10) DEFAULT NULL,
      PRIMARY KEY (`puuid`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");
    
    mysqli_query($database, 
    "DROP TABLE IF EXISTS `accountschampions`;
    CREATE TABLE IF NOT EXISTS `accountschampions` (
      `championName` varchar(30) NOT NULL,
      `puuid` varchar(80) NOT NULL,
      PRIMARY KEY (`championName`,`puuid`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;"
    );
    
    mysqli_query($database,
    "DROP TABLE IF EXISTS `matches`;
    CREATE TABLE IF NOT EXISTS `matches` (
      `puuid` varchar(80) NOT NULL,
      `matchID` varchar(60) NOT NULL,
      PRIMARY KEY (`matchID`,`puuid`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    COMMIT;"
    );
}
?>