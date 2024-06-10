<?php
ini_set('max_execution_time', '300');
//START FUNCTIONS
//-------------------------------------------------------------------------------------
// Set up connection to local database
function establishDBConnection() {
    $connect = mysqli_connect(
        "sql206.infinityfree.com", 
        "if0_36659344", 
        "D46rc91qUZN", 
        "if0_36659344_lolchallenges", 
        3306
    );
    // $connect = mysqli_connect(
    //     "localhost:3306", 
    //     "root", 
    //     "", 
    //     "lolchallenges", 
    // );
    if (mysqli_connect_errno()) {
        echo "Failed to connect to Database. Contact Kiefs" . mysqli_connect_error();
        exit();
    }
    return $connect;
}

// Make request to RIOTAPI with url
function CallAPI($url) {
    $apiKey = "RGAPI-ebc789b2-1a4b-4b17-8359-0654ad692711";
    $cURLConnection = curl_init();

    // echo "Requesting URL: ".$url;

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
// Also grabs accountId
function lookupAndSaveAccount($database, $name, $tagLine) {
    $baseUrl = "https://americas.api.riotgames.com/riot/";
    $route = "account/v1/accounts/by-riot-id/";
    //Clean name string before requesting
    $cleanedName = str_replace(' ', '%20', $name);
    $requestAccountUrl = $baseUrl.$route.$cleanedName.'/'.$tagLine;
    //
    $api_result = CallAPI($requestAccountUrl);
    //
    if (is_object($api_result)) {
        if ($api_result->puuid) {
            $puuid = $api_result->puuid;
            $name = $api_result->gameName;
            $tagLine = $api_result->tagLine;

            echo "Found and saved <strong class='tm-text-primary'>$name#$tagLine</strong>. </br>";
    
            $sqlInsert = "INSERT IGNORE INTO accounts (puuid, name, tagLine) VALUES ('$puuid', '$name', '$tagLine')";
            $database->query($sqlInsert);
            //
            $summonerRoute = "https://na1.api.riotgames.com/lol/summoner/v4/summoners/by-puuid/{$puuid}";
            $api_summoner_result = CallAPI($summonerRoute);
            if (is_object($api_summoner_result)) { 
                $accountId = $api_summoner_result->id;
                $accountIdInsert = "UPDATE accounts SET accountId = '$accountId' WHERE puuid = '$puuid'"; 
                $database->query($accountIdInsert);
            }
        }
    }
}
// Look up match history based on timeline
function queryMatchHistory($database, $puuid, $startStart, $endTime, $maxDays, $name) {
    $days = $startStart;
    $endDay = ($endTime + 1);
    $days = 0 ? "-$days" : "$days";

    // Get rank while looking up matches
    // getArenaRank($database, $name);
    while($days < $maxDays) {
        $currentDate = strtotime("now -$days days");
        $yesterday = strtotime("-$endDay days", $currentDate);
        $days++;
        usleep(250000);
        $baseUrl = "https://americas.api.riotgames.com/";
        $route = "lol/match/v5/matches/by-puuid/$puuid/ids?startTime=$yesterday&endTime=$currentDate&count=20";
    
        $requestAccountUrl = $baseUrl.$route;
    
        $api_result = CallAPI($requestAccountUrl);
        $matchIds = [];
        if (is_array($api_result)) {
            $count = count($api_result);
            foreach ($api_result as $match) {
                $id = $match;
                $matchIds[] = $id;
                $sqlInsert = "INSERT IGNORE INTO matches (puuid, matchID) VALUES ('$puuid', '$id')";
                $database->query($sqlInsert);
            }
            echo "Saved $count records".'</br>';
        } else {
            if ($api_result->status->status_code === 403) {
                echo "API Key EXPIRED. Tell Kiefs to renew".'</br>';
            } else {
                echo "Is not Array";
            }
        }
        return $matchIds;
        if ($days > 40) {
            exit;
        }
    }

}

// Retrieve match info from match id
function queryMatchesHistoryInfo($database, $matchIDs, $summonerPUUID) {
    $baseUrl = "https://americas.api.riotgames.com/";
    $count = 1;
    $firstPlaces = 0;
    foreach($matchIDs as $matchID) {
        $matchIDIdentifier = '';
        if (is_string($matchID)) {
            $matchIDIdentifier = $matchID;
        } else {
            $matchIDIdentifier = $matchID['matchID'];
        }
        if ($count > 400) {
            exit();
        }
        $route = "lol/match/v5/matches/$matchIDIdentifier";
        $requestAccountUrl = $baseUrl.$route;
        $api_result = CallAPI($requestAccountUrl);
        usleep(500000);
        if (!empty(($api_result->info))) {
            $gameMode = $api_result->info->gameMode;
            // CHERRY = "Arena game mode"
            if ($gameMode === "CHERRY") {
                //Mark match as ranQuery = true after executing
                $sqlInsert = "UPDATE matches SET ranQuery = 1, gameMode = '$gameMode' WHERE puuid = '$summonerPUUID' AND matchID = '$matchIDIdentifier'";
                $database->query($sqlInsert);
                $obj = array_column($api_result->info->participants, null, 'puuid')[$summonerPUUID] ?? false;
                $placement = $obj->placement ? $obj->placement : 0;
                $champion = $obj->championName ? $obj->championName : "null";
            
                if ($placement == 1) {
                    $firstPlaces++;
                    echo "Placed first with $champion".'</br>';
                    $sqlInsert = "INSERT IGNORE INTO accountschampions (championName, puuid) VALUES ('$champion','$summonerPUUID')";
                    $database->query($sqlInsert);
                }
            } else {
                $sqlInsert = "UPDATE matches SET ranQuery = 1, gameMode = '$gameMode' WHERE puuid = '$summonerPUUID' AND matchID = '$matchIDIdentifier'";
                $database->query($sqlInsert);
            }
        }
        $count++;
    }
    return $firstPlaces;
}
//GET MATCH HISTORY AND INFO FOR CURRENT DAY 
function getRecentMatches($database, $puuid, $name) {
    $recentMatchesById = queryMatchHistory($database, $puuid, '0', '1', 1, $name);
    if (is_array($recentMatchesById) && count($recentMatchesById) > 0) {
        $firstPlaces = queryMatchesHistoryInfo($database, $recentMatchesById, $puuid);
        if (empty($firstPlaces) && $firstPlaces === 0) {
            echo "Found no first places today. Try again in 15 minutes if your recent win is not showing up";
        }
    } else {
        echo "Found no recent matches";
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
function getAccount($database, $name, $tagLine) {
    $result = mysqli_query($database, "SELECT puuid, name, accountId FROM accounts WHERE name = '$name' LIMIT 20");
    $resultRow = $result->fetch_assoc();
    if ($resultRow) {
        if (!empty($resultRow['accountId'])) {
            return ['puuid'=>$resultRow['puuid'], 'name'=>$resultRow['name'], 'accountId'=>$resultRow['accountId']];
        } else {
            if (empty($tagLine) || $tagLine === '') {
                echo 'Enter Tagline </br>';
            } else {
                summonerLookup($database, $name, $tagLine);
            }
        }
    }
}
// GET LEADERBOARDS
function createLeaderBoard($database) {
    $sql = 
    "SELECT accounts.puuid, accounts.name, COUNT(accountschampions.puuid) AS Total
    FROM accounts
    LEFT JOIN accountschampions ON accounts.puuid = accountschampions.puuid
    GROUP BY accounts.puuid,accounts.name
    ORDER BY Total DESC
    LIMIT 4";
    $leaderBoard = $database->query($sql);
    $createdLeaderBoard = [];
    while($row = mysqli_fetch_array($leaderBoard)) {
        $name = $row['name'];
        $total = $row['Total'];
        $createdLeaderBoard[] = !empty($row) ? $row : '';
        echo "$name: $total </br>";
    }
    return $createdLeaderBoard;
}
// Get current rank in Arena
function getArenaRank($database, $name) {    
    if ($name) {
        $account = getAccount($database, $name, '');
        var_dump($account);
        $accountId = $account['accountId'];
        $leagueRoute = "https://na1.api.riotgames.com/lol/league/v4/entries/by-summoner/$accountId";
        //
        $api_result = CallAPI($leagueRoute);
        //
        if (is_array($api_result)) {
            var_dump($api_result);
        }
    }
}
// END FUNCTIONS
//-------------------------------------------------------------------------------------
//Set DB

//-------------------------------------------------------------------------------------

// PHP AJAX FUNCTIONS
//-------------------------------------------------------------------------------------
if (isset($_POST['action'])) {
    $database = establishDBConnection();
    if ($_POST['action'] === 'createLeaderBoard') {
        return createLeaderBoard($database);
    }
    $name = !empty($_POST["name"]) ? $_POST["name"] : '';
    $tagLine = !empty($_POST["tagLine"]) ? $_POST["tagLine"] : '';
    if ($name != '' ) {
        $account = getAccount($database, $name, $tagLine);
        if (!empty($account['name'])) {
            $summonerPUUID = $account['puuid'];
            echo "<h4>Summoner Puuid: ".$summonerPUUID."</h4>".'</br>'."<h4>Summoner Name: ".$account['name'].'</h4></br>';
            switch ($_POST['action']) {
                case 'queryMatches':
                    return queryMatches($database, $summonerPUUID, $name);
                    break;
                case 'getChampions':
                    return getChampions($name, $database);
                    break;
                case 'queryMatchInfo':
                    return getMatchInfo($database, $summonerPUUID, $name);
                    break;
                case 'getRecentMatches': 
                    return getRecentMatches($database, $summonerPUUID, $name);
                    break;
            }
        } else {
            return summonerLookup($database, $name, $tagLine);
        }
    } else {
        echo "Missing Name";
    }
}

function queryMatches($database, $summonerPUUID, $name) {
    queryMatchHistory($database, $summonerPUUID, "0", "1", 40, $name);;
    exit;
}

function getMatchInfo($database, $summonerPUUID, $name) {
    $matchResult = mysqli_query($database, "SELECT * FROM matches WHERE puuid = '$summonerPUUID' AND ranQuery = '0' ORDER BY matchID");
    $matchIDs = [];
    echo 'Fetching '.mysqli_num_rows($matchResult).' number of matches. Querying each at 0.65 seconds';
    while($row = mysqli_fetch_array($matchResult)) {
        $matchIDs[] = !empty($row) ? $row : '';
    }
    queryMatchesHistoryInfo($database, $matchIDs, $summonerPUUID);
    exit;
}

function getChampions($name, $database) {
    $champs = getSummonerChampionsByName($name, $database);
    echo 'Placed first with '.count($champs).' total champions </br>';
    $returnChamps = '<div class="champion-pool">';
    foreach ($champs as $champ) {
        $returnChamps = $returnChamps."
        <div class='won-champion tm-text-primary'>
            <img src='https://ddragon.leagueoflegends.com/cdn/14.11.1/img/champion/$champ.png'/>
            <span>$champ</span></br>
        </div>
        ";
    }
    echo $returnChamps.'</div>';
}

function summonerLookup($database,$name, $tagLine) {
    if ($name === '' || $tagLine === '') {
        echo '<p>Summoner not known, or missing name / tagLine</p>';
    } else {
        lookupAndSaveAccount($database, $name, $tagLine);
    }
    exit;
}
// END PHP AJAX FUNCTIONS
//-------------------------------------------------------------------------------------
?>