<?php
   $link = mysql_pconnect('localhost', 'mail', 'activate') or die('Could not connect: ' . mysql_error());
   mysql_select_db('tictacwhoa') or die('Could not select database');
  
   $json = file_get_contents('php://input'); 
   $in = cleanInput(json_decode($json));
   $out = "";
   switch ($in->action) {
      case "login":
         $result = mysql_query("select * from player where fb_id='".$in->data->fb_id."'");
         $out = mysql_fetch_assoc($result);
         if (!$out) {
            $fields = array('id', 'player', 'email', 'fb_id', 'photo', 'created');
            $sql = makeInsert("player", $fields, $in->data);
            doLog("[LOGIN] $sql");

            // Create new record
            mysql_query($sql);   
            
            // Then fetch the newly created record to return to client
            $out = getRecord('player', $in->data->fb_id, 'fb_id');
            $out["registered"] = true;
         }
         if ($out['id']) {
            setcookie("id", $out['id']);
         }
         break;
      case "getPlayer":
         $out = getRecord('player', $in->data->id);
         
         break;
      case "getGame":
         $out = getRecord('game', $in->data->id);
         
         $up = $out["player".($out['player_up']+1)."_id"];
         $prec = getRecord('player', $up);
         if ($prec['bot']) {
            if (preg_match("/(game\d\d)(\d\d)/", $out['last_move'], $matches)) {
               $g = "game".$matches[2];
            }
            automove($out['id'], $out['player_up'], $g);
         }
         break;
      case "getMoves":
         $result = mysql_query("select * from move where for_player_id='".$in->data->for_player_id."' and game_id='".$in->data->game_id."' and seen!=1");
         $out = new stdClass();
         $moves = array();

         while ($move = mysql_fetch_assoc($result)) {
            $moves[] = $move;
         }
         $out->moves = $moves;
         break;
       case "getGames":
         if ($in->data->id) {
            $sql = "select * from game where (player1_id='".$in->data->id."' or player2_id='".$in->data->id."') and state!='complete' and draw=false";
            //doLog("[getGames] $sql");

            $result = mysql_query($sql);
            $out = new stdClass();
            $games = array();
            $players = array();

            while ($game = mysql_fetch_assoc($result)) {
               $games[] = $game;
               if ($game['player1_id'] == $in->data->id) {
                  if (!$players[$game['player2_id']]) {
                     $players[$game['player2_id']] = getRecord('player', $game['player2_id']);
                  }
               } else if ($game['player2_id'] == $in->data->id) {
                  if (!$players[$game['player1_id']]) {
                     $players[$game['player1_id']] = getRecord('player', $game['player1_id']);
                  }
               }
            }
            
            $out->players = $players;
            $out->games = $games;
         }   
         break;
       case "getPlayers":
         $result = mysql_query("select * from player");
         $out = new stdClass();
         $players = array();

         while ($player = mysql_fetch_assoc($result)) {
            $players[] = $player;
         }
         $out->players = $players;
         break;
       case "saveGame":
         $upd = "update game set ";
         $arr = array();

         foreach ($in->data as $key=>$val) {
            $arr[] = $key."='".$val."'";   
         }
         $upd .= implode($arr, ", ");
         doLog("[saveGame] ".$upd);
         mysql_query($upd);
         $out = getRecord('game', $in->data->id);

         break;
       case "invites":
         $result = mysql_query("select * from invite where player2_id='".$in->data->id."' and declined=0 and started=0 and canceled=0");
         $invites = array();
         $requests = array();
         $players = array();
         while ($invite = mysql_fetch_assoc($result)) {
            if (!$players[$invite['player1_id']]) {
               $player = getRecord('player', $invite['player1_id']);
               $players[$invite['player1_id']] = $player;
            }
            $invite["player"] = $players[$invite['player1_id']]["player"];
            $invite["photo"] = $players[$invite['player1_id']]["photo"];
            $invites[] = $invite;
         }
         $result = mysql_query("select * from invite where player1_id='".$in->data->id."' and declined=0 and started=0 and canceled=0");
         while ($request = mysql_fetch_assoc($result)) {
            if (!$players[$request['player2_id']]) {
               $player = getRecord('player', $request['player2_id']);
               $players[$request['player2_id']] = $player;
            }
            $request["player"] = $players[$request['player2_id']]["player"];
            $request["photo"] = $players[$request['player2_id']]["photo"];
            $requests[] = $request;
         }
          
         $out = new stdClass();
         $out->invites = $invites;
         $out->players = $players;
         $out->requests = $requests;

         break;
      
      case "invite":
         $fields = array('player1_id', 'player2_id', 'created');
         $sql = makeInsert("invite", $fields, $in->data);
         doLog($sql);
         mysql_query($sql);
         $id = mysql_insert_id();
         $out = getRecord('invite', $id);

         $opponent = getRecord('player', $in->data->player2_id);
         if ($opponent['bot']) {
            $data = new stdClass();
            $data->id = $id;
            $data->player1_id = $in->data->player1_id;
            $data->player2_id = $in->data->player2_id;
            start($data);
         }

         break;
      case "cancelInvite":
         if ($in->data->id) {
            $invite = getRecord('invite', $in->data->id);
            $player = getRecord('player', $invite['player2_id']);

            $sql = "update invite set canceled=1 where id='".$in->data->id."'";
            doLog("[cancelInvite] Canceling invite to ".$player['player']." [invite id {$in->data->id}]");
            doLog("[cancelInvite] $sql");
            mysql_query($sql);
            $out = new stdClass();
            $out->action = "notify";
            $out->data = new stdClass();
            $out->data->msg = "Canceled invitation to {$player['player']} [invite id {$in->data->id}]";
         }
         break;
      case "ackMove":
         $sql = "update invite set seen=1 where id={$in->data->id}";
         doLog("[ackMove] $sql");
         mysql_query($sql);
         $out = new stdClass();
         $out->action = "notify";
         $out->data = new stdClass();
         $out->data->msg = "Move ID {$in->data->id} acknowledged";

         break;
      case "start":
         $data = $in->data;
         $out = new stdClass();
         $out->game = start($data);
         $out->players = new stdClass();
         $p1 = getRecord('player', $data->player1_id);
         $p2 = getRecord('player', $data->player2_id);
         $out->players->{$data->player1_id} = $p1;
         $out->players->{$data->player2_id} = $p2;
	
	 $sql = "update player set started=started+1 where (id={$data->player1_id} or id={$data->player2_id})";
	 mysql_query($sql);
         break;
      case "ackMove":
         $sql = "update move set seen=1 where id={$in->data->id}";
         doLog("[ackMove] $sql");
         mysql_query($sql);
         $out = new stdClass();
         $out->action = "notify";
         $out->data = new stdClass();
         $out->data->msg = "Move ID {$in->data->id} acknowledged";

         break;
      case "move":
         $fields = array('player', 'player_id', 'for_player_id', 'game_id', 'move', 'mark', 'created');
         if (!$in->data->mark) {
            $in->data->mark = ($in->data->player==1) ? "O" : "X";
         }
         if ($in->data->player_id==$in->data->for_player_id) {
            $game = getRecord("game", $in->data->game_id);

            if ($game['player1_id'] == $in->data->player_id) {
               $in->data->player_id = $game['player2_id'];
            } else if ($game['player2_id'] == $in->data->player_id) {
               $in->data->player_id = $game['player1_id'];
            }
         }
         $sql = makeInsert("move", $fields, $in->data);
         doLog("[MOVE] $sql");
         mysql_query($sql);
         
         $id = mysql_insert_id();
         $out = getRecord('move', $id);
         
         // Store game state and set current player
         $sql = "update game set game='".$in->data->game."', player_up='".($in->data->player^1)."', last_move='".$in->data->move."' where id='".$in->data->game_id."'";
         doLog("[MOVE] Updated game: $sql");
         mysql_query($sql);
         
         if (preg_match("/(game\d\d)(\d\d)/", $in->data->move, $matches)) {
            $g = "game".$matches[2];
         }

         automove($in->data->game_id, $in->data->player, $g);
         break;

      case "update":
         if (($in->table) && ($in->data->id)) {
            $sql = "update ".$in->table." set ";
            
            $fields = array();
            foreach ($in->data as $key=>$val) {
               if ($key != "id") {
                  $fields[] = $key."='".$val."'";
               }
            }
            
            $sql .= implode($fields, ", ");
            $sql .= " where id='".$in->data->id."'";

            mysql_query($sql);
            $result = mysql_query("select * from ".$in->table." where id='".$in->data->id."'");
            $out = mysql_fetch_assoc($result);

            doLog("[UPDATE] $sql");
         }
         break;
   
      case "win":
         if ($in->data->winner && $in->data->game_id) {
            $sql = "update game set winner='{$in->data->winner}', game='{$in->data->game}', state='complete' where id='{$in->data->game_id}'";
            doLog("[WIN] $sql");
            $result = mysql_query($sql);
            
            $winner = getRecord('player', $in->data->winner);
            if ($winner) {
               $sql = "update player set wins=".($winner['wins']+1).", plays=".($winner['plays']+1)." where id=".$winner['id'];
               $result = mysql_query($sql);
            }
            $out = getRecord('game', $in->data->game_id);

            if ($out) {
               $loserid = ($out['player1_id'] == $in->data->winner) ? $out['player2_id'] : $loserid = $out['player1_id'];
               $loser = getRecord('player', $loserid);
               $sql = "update player set plays=".($loser['plays']+1).", losses=".($loser['losses']+1)." where id=".$loser['id'];
               $result = mysql_query($sql);
            }
         }
         break;
        
      case "draw":
         if ($in->data->winner && $in->data->game_id) {
            $sql = "update game set winner='0', game='{$in->data->game}', state='complete', draw=true where id='{$in->data->game_id}'";
            doLog("[DRAW] $sql");
            $result = mysql_query($sql);
            
            $out = getRecord('game', $in->data->game_id);
            $p1 = getRecord('player', $out['player1_id']);
            $p2 = getRecord('player', $out['player2_id']);
            $sql = "update player set plays=plays+1, ties=ties+1 where id={$p1['id']} or id={$p2['id']}";
            mysql_query($sql);
         }
         break;

      case "getContent":
         if ($in->data->name) {
            $out = new stdClass();
            $out->name = $in->data->name;
            if (file_exists("pages/{$in->data->name}.html")) {
               $out->content = file_get_contents("pages/{$in->data->name}.html");
            } else {
               $out->content = "";
               $out->error = "File not found.";
            }
         }
         break;
 }
   
   function makeInsert($table, $fields, $data) {
      $out = "INSERT INTO $table (" . implode($fields, ", ") .") VALUES ";
      $vals = array();

      foreach ($fields as $field) {
         if ($field == "created") {
            $vals[] = "now()";
         } else {
            $vals[] = "'".$data->$field."'";
         }
      }

      $out .= "(" . implode($vals, ", ") . ")";

      return $out;
   }

   function cleanInput($in) {
      if ($in) {
	      switch (gettype($in)) {
		 case "object":
		    $new = new stdClass();
		    break;
		 case "array":
		    $new = array();
		    break;
	      }

	      foreach ($in as $key=>$val) {
		 if (is_object($val)) {
		    $new->$key = cleanInput($val);
		 } else if (is_array($val)) {
		    $new[$key] = cleanInput($val);
		 }
		 if (is_scalar($val)) {
		    if (is_object($new)) {
		       $new->$key = mysql_real_escape_string($val);
		    } else if (is_array($new)) {
		       $new[] = mysql_real_escape_string($val);
		    }
		 } 
	      }
	      return $new;
      } 
   }
   
   function getRecord($tbl, $id, $field="id") {
      $sql = "select * from $tbl where $field='$id'";
//      doLog("[getRecord] $sql");
      $dbh = mysql_query($sql);
      $out = mysql_fetch_assoc($dbh);

      return $out;
   }

   function getRecords($tbl, $id, $field="id", $extra="") {
      $sql = "select * from $tbl where $field='$id' $extra";
      //doLog("[getRecord] $sql");
      $dbh = mysql_query($sql);
      $out = new stdClass();
      $items = array();

      while ($item = mysql_fetch_assoc($dbh)) {
         $items[] = $item;   
      }
      $out->$tbl = $items;

      return $out;
   }

   function pickMove($game, $g, $me, $skill=50) {
      doLog("=============================\n[pickMove] game: ".print_r($game, true)." g: $g   me: $me");
      if (rand(0, 100) > $skill) {
         $out = pickRandom($game);
         doLog("[pickMove] Low skill bot pick (skill: $skill, pick: $out)");
         return $out;
      }
      $out = "";
      for ($r=0; $r<3; $r++) {
         doLog("[pickMove] Checking row $r ...{$g}{$r}0: ".$game["{$g}{$r}0"]."  {$g}{$r}1: ".$game["{$g}{$r}1"]."  {$g}{$r}2: ".$game["{$g}{$r}2"]);
         if ($game["$g{$r}0"] == $me) {
            if (($game["$g{$r}1"]==$me) && ($game["$g{$r}2"]=="")) {
               $out =  "$g{$r}2";
            }
            if (($game[$g.$r."1"]=="") && ($game["$g{$r}2"]==$me)) {
               $out =  $g.$r."1";
            }
         }
         if ($game[$g.$r."1"] == $me) {
            if (($game[$g.$r."0"]=="") && ($game[$g.$r."2"]==$me)) {
               $out =  $g.$r."0";
            }
            if (($game[$g.$r."0"]==$me) && ($game[$g.$r."2"]=="")) {
               $out =  $g.$r."2";
            }
         }
      }

      for ($c=0; $c<3; $c++) {
         doLog("[pickMove] Checking col $c ...{$g}0{$c}: ".$game["{$g}0{$c}"]."  {$g}1{$c}: ".$game["{$g}1{$c}"]."  {$g}2{$c}: ".$game["{$g}2{$c}"]);
         if ($game["{$g}0{$c}"] == $me) {
            if (($game["{$g}1{$c}"]==$me) && ($game["{$g}2{$c}"]=="")) {
               $out =  $g."2".$c;
            }
            if (($game[$g."1".$c]=="") && ($game[$g."2".$c]==$me)) {
               $out =  $g."1".$c;
            }
        }
        if ($game[$g."1".$c] == $me) {
            if (($game[$g."0".$c]=="") && ($game[$g."2".$c]==$me)) {
               $out =  $g."0".$c;
            }
            if (($game[$g."0".$c]==$me) && ($game[$g."2".$c]=="")) {
               $out =  $g."2".$c;
            }
         }
      }

      if (($game[$g."00"]==$me) && ($game[$g."11"]==$me) && ($game[$g."22"]=="")) {
         $out =  $g."22";
      }
      if (($game[$g."00"]==$me) && ($game[$g."11"]=="") && ($game[$g."22"]==$me)) {
         $out =  $g."11";
      }
      if (($game[$g."00"]=="") && ($game[$g."11"]==$me) && ($game[$g."22"]==$me)) {
         $out =  $g."00";
      }

      if (($game[$g."02"]==$me) && ($game[$g."11"]==$me) && ($game[$g."20"]=="")) {
         $out =  $g."20";
      }
      if (($game[$g."02"]==$me) && ($game[$g."11"]=="") && ($game[$g."20"]==$me)) {
         $out =  $g."11";
      }
      if (($game[$g."02"]=="") && ($game[$g."11"]==$me) && ($game[$g."20"]==$me)) {
         $out =  $g."02";
      }
      if ($out) {
         doLog("Picked $out");
      } else {
         doLog("No pick made.");
      }
      return $out;
   }
   
   function encodeGames($games) {
      $out = array();

      for ($r=0; $r<3; $r++) {
         for ($c=0; $c<3; $c++) {
            $tmp = encodeGame($games["games"]["game$r$c"], "game$r$c");
            $out[] = dechex($tmp);
         }
      }
      $out[] = dechex(encodeGame($games["game"], ""));

      return implode(":", $out);
   }

   function encodeGame($game, $g) {
      $val = array("X"=>1, "O"=>512);
      $out = 0;
      
      for ($r=0; $r<3; $r++) {
         for ($c=0; $c<3; $c++) {
            if ($game["{$g}{$r}{$c}"]!="") {
               $out += $val[$game["{$g}{$r}{$c}"]];
            }
            $val['X'] *= 2;
            $val['O'] *= 2;
         }
      }
      return $out;
   }

   function decodeGames($gameStr) {
      $out = array("game"=>array(), "games"=>array());
      $arr = explode(":", $gameStr);

      for ($r=0; $r<3; $r++) {
         for ($c=0; $c<3; $c++) {
            $out["games"]["game$r$c"] = decodeGame(hexdec(array_shift($arr)), "game$r$c");
         }
      }
      $out["game"] = decodeGame(hexdec(array_shift($arr)), "");

      return $out;
   }

   function decodeGame($enc, $g="") {
      $valX = 1;
      $valO = 512;
      $game = array();

      for ($r=0; $r<3; $r++) {
         for ($c=0; $c<3; $c++) {
            if ($enc & $valX) {
               $game["$g$r$c"] = "X";
            } else if ($enc & $valO) {
               $game["$g$r$c"] = "O";
            } else {
               $game["$g$r$c"] = "";
            }
            $valX *= 2;
            $valO *= 2;
         }
      }
      return $game;
   }
   
   function pickRandom($game) {
      $avail = array();
      $out = "";

      foreach ($game as $id=>$val) {
         if ($val=="") {
            $avail[] = $id;
         }
      }
      if (count($avail)) {
         $out = $avail[rand(0, count($avail)-1)];
      } else {
         $out = "-";
      }
      return $out;
   }

   function automove($game_id, $player, $g) {
      $mygame = getRecord('game', $game_id);
      $opponent = getRecord('player', $mygame["player".(($player^1)+1)."_id"]);

      if ($opponent['bot']) {
         doLog("[BOT MOVE] Got bot opponent");
         doLog("[BOT MOVE] game:$g  player:$player  game_id:$game_id");

         $game = decodeGames($mygame['game']);
         $me = ($player == 1) ? "O" : "X";
         $them = ($player == 1) ? "X" : "O";
   
         doLog("[BOT MOVE] Me: $me  Them: $them  g: $g");
         
         if (preg_match("/game(\d\d)(\d\d)/", $mygame['last_move'], $matches)) {
            $gid = $matches[2];
            doLog("[BOT MOVE] gid: $gid");
         }
         
         $game['game'] = syncGames($game['games']);

         doLog("[BOT MOVE] Game state: ".print_r($game['game'], true));
         if ($game['game'][$gid]!="") {   
            doLog("[BOT MOVE] Current game already won. Picking new game to play within...");
            $gid = pickMove($game['game'], "", $me);
            if (!$gid) {
               $gid = pickMove($game['game'], "", $them);
               
               if (!$gid) {
                  $gid = pickRandom($game['game']);
               }
            }
            if (!$gid) {
               doLog("[BOT MOVE] Looks like a DRAW. Not making any moves.");
               return false;
            }
            $g = "game$gid";
            doLog("[BOT MOVE] New current game: $g");
         }

         $pick = pickMove($game['games'][$g], $g, $me);

         if ($pick) { doLog("[BOT MOVE] GOT PICK FOR WIN: $pick"); }
         if (!$pick) {
            doLog("[BOT MOVE] No winning moves. Looking for block...");
            $pick = pickMove($game['games'][$g], $g, $them);
            if ($pick) { doLog("[BOT MOVE] GOT PICK FOR BLOCK: $pick"); }
         }

         if (!$pick) {
            doLog("[BOT MOVE] No blocking moves. Picking at random");
            $open = array();
            for ($r=0; $r<3; $r++) {
               for ($c=0; $c<3; $c++) {
                  if ($game["games"][$g][$g.$r.$c]=="") {
                     $open[] = $g.$r.$c;
                  }
               }
            }
            if (count($open)>0) {
               $pick = $open[rand(0, count($open)-1)];
            }
            doLog("[BOT MOVE] last resort: $pick");
         }

         if ($pick) {
            $game["games"][$g][$pick] = $them;
            // doLog("[BOT MOVE] encoded: " . print_r($mygame['game'], true). " decoded: " . print_r($game, true));
            $botmove = "insert into move (id, game_id, player, player_id, for_player_id, move, mark, moved, created, seen) values (null, '{$game_id}', '".($player^1)."', '{$opponent['id']}', '".($mygame['player'.($player+1).'_id'])."', '{$pick}', '$them', now(), now(), 0)";
            mysql_query($botmove);
            doLog("[BOT MOVE] $botmove");

            $sql = "update game set game='".encodeGames($game)."', last_move='$pick', player_up='{$player}' where id='{$game_id}'";
            mysql_query($sql);
            doLog("[BOT MOVE] $sql");
         }
      }
   }
   
   function syncGames($games) {
      $out = array();

      for ($r=0; $r<3; $r++) {
         for ($c=0; $c<3; $c++) {
            $g = "game{$r}{$c}";
            $out[$r.$c] = checkWin($games[$g], $g);
         }
      }
      doLog("[SYNC GAMES] Game state: ".print_r($out, true));
      return $out;
   }

   function checkWin($game, $g) {
      $winner="";

      $id = "";

      // Check for diagnal wins
      if (($game["{$g}00"]!="") && (($game["{$g}00"] == $game["{$g}11"]) && ($game["{$g}00"] == $game["{$g}22"]))) {
         $winner = $game["{$g}00"];
         doLog("[checkWin] Won diagnol for " . $game[$g."00"] . "  game: $g  spots: 00/11/22");
      }
      if (($game["{$g}02"]!="") && (($game["{$g}02"] == $game["{$g}11"]) && ($game["{$g}02"] == $game["{$g}20"]))) {
         $winner = $game["{$g}02"];
         doLog("[checkWin] Won diagnol for " . $game[$g."02"] . "  game: $g  spots: 02/11/20");
      }
      
      // Check for wins by row and by column
      for ($r=0; $r<3; $r++) {
         $id = $r;
         if (($game[$g.$id."0"]!="") && 
            (($game[$g.$id."0"] == $game[$g.$id."1"]) && 
             ($game[$g.$id."0"] == $game[$g.$id."2"]))) {
            $winner = $game[$g.$id."0"];
            doLog("[checkWin] Won across for " . $game[$g.$id."0"] . "  game: $g  spots: {$id}0/{$id}1/{$id}2");
         }
      }
      
      for ($c=0; $c<3; $c++) {
         $id = $c;
         if (($game["{$g}0".$id]!="") && 
            (($game["{$g}0".$id] == $game["{$g}1".$id]) && 
             ($game["{$g}0".$id] == $game["{$g}2".$id]))) {
            $winner = $game["{$g}0".$id];
            doLog("[checkWin] Won down for " . $game[$g.$id."0"] . "  game: $g  spots: {$id}0/{$id}1/{$id}2");
         }
      }

      $space = 0;
      foreach ($game as $i=>$val) {
         if ($val=="") {
            $space++;
         }
      }
      if (($space==0) && (!$winner)) {
         $winner = "-";
         doLog("DRAW GAME");
      }
      
      return $winner;
   }

   function deleteRecord($tbl, $id, $key="id") {
      if ($tbl && $id) {
         $sql = "delete from `{$tbl}` where {$key}='{$id}'";
         doLog("[DELETE] $sql");
         $result = mysql_query($sql);
      }
      return $result;
   }

   function start($data) {
      if ($data->id) {
         $invite = getRecord('invite', $data->id);
         $sql = "update invite set started=1 where id={$data->id}";
         mysql_query($sql);
         
         $data->player1_id = $invite['player1_id'];
         $data->player2_id = $invite['player2_id'];
         $data->player_up = 0;

         $fields = array('player1_id', 'player2_id', 'player_up', 'game', 'created');
         if (!$data->game) {
            $data->game = encodeGames(genBoard());
         }
         $sql = makeInsert("game", $fields, $data);
         doLog("[START] $sql");
         mysql_query($sql);
         $id = mysql_insert_id();
         $out = getRecord('game', $id);

         $p1 = getRecord('player', $data->player1_id);
         if ($p1['bot']) {
            automove($id, 0, "game11");
         }
      } 
      return $out;
   }
   
   function genBoard() {
      $out = array();
      $game = array();
      $games = array();

      for ($r=0; $r<3; $r++) {
         for ($c=0; $c<3; $c++) {
            $games["game$r$c"] = array();
            $game["$r$c"] = "";
            for ($r1=0; $r1<3; $r1++) {
               for ($c1=0; $c1<3; $c1++) {
                  $games["game$r$c"]["game$r$c$r1$c1"] = "";
               }
            }
         }
      }
      $out["game"] = $game;
      $out["games"] = $games;

      return encodeGames($out);
   }

   function doLog($what) {
      file_put_contents("/tmp/ttw.log", $what."\n", FILE_APPEND | LOCK_EX);
   }

   header("Content-type: application/json");
   if ($out) {
      print json_encode($out);
   }
?>
