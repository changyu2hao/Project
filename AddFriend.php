<?php include("./ProjectCommon/Header.php"); ?>
<?php include('./ProjectCommon/Footer.php'); ?>
<?php session_start();?>
<html>
    <head>
        <title>Home</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css">
    </head>
    <body>
        <?php 
        $Userid=$_SESSION["Userid"];
        $friendIdTxt=$_POST['friendIdTxt'];
        $_SESSION['friendIdTxt']=$_POST['friendIdTxt'];
        $dbConnection = parse_ini_file("db_connection.ini");
        $date=date("Y/m/d");
        if(empty($Userid))
        {
            header("Location: Login.php"); 
        }
        extract($dbConnection);
        $myPdo = new PDO($dsn, $user, $password);
        $sqlstatementname='select Name FROM User where UserId=:Id';//staement for the name
        $pStmtname = $myPdo -> prepare($sqlstatementname);
        $pStmtname ->execute( [ 'Id' => $Userid] );
        $name = $pStmtname->fetchColumn();
        if(isset($_POST['Send']))
    {
        //Connection to DBO            
        $dbConnection = parse_ini_file("db_connection.ini");        	
        extract($dbConnection);
        $myPdo = new PDO($dsn, $user, $password); 
        //checking if ID exists in application         
        $sqlStatement = 'SELECT * FROM User WHERE UserId = :UserID ';
        $pStmt = $myPdo->prepare($sqlStatement);       
        $pStmt ->execute([':UserID' => $friendIdTxt]);      
        $ExsitUser = $pStmt->fetch();        
        
        //user cannot send a request to someone who is already a friend
        //a) if user is a requester and invite was accepeted:      
        //b) if user is a requestee and invite was accepeted
        $sqlStatement = 'SELECT * FROM friendship '
                . 'WHERE Friend_RequesterId = :resquesterId AND Friend_RequesteeId = :requesteeId AND Status = :status';
        $pStmt = $myPdo->prepare($sqlStatement);        
        $pStmt ->execute(array(':resquesterId' => $_SESSION['friendIdTxt'] , ':requesteeId' => $_SESSION['Userid'] , ':status' => 'accepted' ));      
        $requestee = $pStmt->fetch(); 
        
        $sqlStatement = 'SELECT * FROM friendship '
                . 'WHERE Friend_RequesterId = :resquesterId AND Friend_RequesteeId = :requesteeId AND Status = :status';
        $pStmt = $myPdo->prepare($sqlStatement);        
        $pStmt ->execute(array(':resquesterId' => $_SESSION['Userid'] , ':requesteeId' => $_SESSION['friendIdTxt'], ':status' => 'accepted' ));      
        $requester = $pStmt->fetch(); 
        
        //if user is a requestee and invite is pending:
        $sqlStatement = 'SELECT * FROM friendship '
                . 'WHERE Friend_RequesterId = :friend AND Friend_RequesteeId = :user AND Status = :status';
        $pStmt = $myPdo->prepare($sqlStatement);        
        $pStmt ->execute(array(':user' => $_SESSION['Userid'] , ':friend' => $_SESSION['friendIdTxt'], ':status' => 'request' ));      
        $waiting = $pStmt->fetch();  
        
        //if user is a requester and invite is pending:
        $sqlStatement = 'SELECT * FROM friendship '
                . 'WHERE Friend_RequesterId = :user AND Friend_RequesteeId = :friend AND Status = :status';
        $pStmt = $myPdo->prepare($sqlStatement);        
        $pStmt ->execute(array(':user' => $_SESSION['Userid'] , ':friend' => $_SESSION['friendIdTxt'], ':status' => 'request' ));      
        $waitingFriend = $pStmt->fetch(); 
        
        //checking if this request was already sent
        if ($waitingFriend != null){
            $validateError = "You can't send this request twice. Invitation is still pending";
        }       
        else {    
            //retrieving information on requestee
            $sqlStatement = "SELECT UserId, Name FROM user WHERE UserId = :requesteeId";
            $pStmt = $myPdo->prepare($sqlStatement);        
            $pStmt ->execute([':requesteeId' => $_SESSION['friendIdTxt']]);  
            $identity = $pStmt->fetch();
            
            //if user is not in social media yet
            if ($ExsitUser == null){
                $validateError = "User is not in this social media yet!";
            }       
            //user cannot send a friend request to himself/herself
            else if ($_SESSION['Userid'] == $_SESSION['friendIdTxt']) {
                $validateError = "You cannot send a friend request to yourself!";
            }
            //user cannot send a request to someone who is already a friend
            else if ($requester != null || $requestee != null){
                $validateError = "This user is already your friend!";
            }
            //If A sends a friend request to B, while A has a friend request from B 
            //waiting for A to accept, A and B become friends.
            else if ($waiting != null)  {
                //update requestee status
                $sqlStatement = "UPDATE friendship SET status = 'accepted' "
                    . "WHERE Friend_RequesterId = :requesteeId AND Friend_RequesteeId = :requesterId "; 
                $pStmt = $myPdo->prepare($sqlStatement);        
                $pStmt ->execute(array(':requesterId' => $_SESSION['Userid'] , ':requesteeId' => $_SESSION['friendIdTxt'] ));      
                $pStmt->commit;
                //update requester status            
                $sqlStatement = "INSERT INTO friendship (Friend_RequesterId, Friend_RequesteeId, Status) "
                        . "VALUES (:requesterId, :requesteeId, :status)";
                $pStmt = $myPdo->prepare($sqlStatement);        
                $pStmt ->execute(array(':requesterId' => $_SESSION['Userid'] , ':requesteeId' => $_SESSION['friendIdTxt'], ':status' => 'accepted' ));      
                $pStmt->commit;    
                $validateError = "You and  ". $identity[1] . " (ID:" . $identity[0] . ") are now friends.";        
            }          
            //sending the invitation which will be pending, until accepted by new friend
            else { 
                //inserting friendship into table
                $sqlStatement = "INSERT INTO friendship (Friend_RequesterId, Friend_RequesteeId, Status) "
                        . "VALUES (:requesterId, :requesteeId, :status)";
                $pStmt = $myPdo->prepare($sqlStatement);        
                $pStmt ->execute(array(':requesterId' => $_SESSION['Userid'] , ':requesteeId' => $_SESSION['friendIdTxt'], ':status' => 'request' ));      
                $pStmt->commit;     
                //confirmation message
                $validateError = "Your request was sent to ". $identity[1] . " (ID:" . $identity[0] . "). "
                        . "<br>" . "&nbsp &nbsp &nbsp" ."Once " . $identity[1] . " accepts your request, you and ". $identity[1] . " will be friends "
                        . "and will be able to see each others' shared albums.";
            }  
        }
    }
        ?>
        <div class="container">
            <form method = "post" action = "AddFriend.php">
            <h1 class="col-md-6 col-md-offset-4">Add Friend</h1>
            <div class="row vertical-margin form-group">
            <div class="col-md-6">
                <p>Welcome <?php echo '<strong>'.$name.'</strong>'?>!(not you? change user <a href="Login.php">here</a>)</p>            
            </div>
            </div>
            <div class="row vertical-margin form-group">
            <div class="col-md-6">
                <p>Enter the ID of the user you want to be friend with</p>            
            </div>
            </div>
            <div class="row vertical-margin form-group">
            <div class="col-md-3">
                ID:
            </div>
            <div class="col-md-3">
                  <input type = "text" id='friendIdTxt' name='friendIdTxt'  value='<?php print $_SESSION['friendIdTxt']; ?>' >  
            </div>
            <div class="col-md-3">
                  <button name="Send" type="submit" class="btn btn-success">Send Friend Request</button>        
            </div>
            <br><div class='col-lg-10' style='color:red'>&nbsp &nbsp &nbsp <?php print $validateError;?></div>
            </div>
            </form>
        </div>
    </body>
</html>>
