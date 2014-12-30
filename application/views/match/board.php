<!DOCTYPE html>

<html>
	<head>
    <link href="<?= base_url();?>css/board.css" rel="stylesheet" type="text/css" />
	<script src="http://code.jquery.com/jquery-latest.js"></script>
	<script src="<?php echo base_url(); ?>js/jquery.timers.js"></script>
    <script src="http://code.jquery.com/ui/1.9.1/jquery-ui.js"></script>
	<!--<script src="<?= base_url();?>js/board.js"></script>-->
	
	<script>
		var otherUser = "<?php echo $otherUser->login; ?>";
		var user = "<?php echo $user->login; ?>";
		var status = "<?php echo $status; ?>";
        var usrnum = <?php echo $usrnum; ?>;
        var match_state = 1;
		
                function getMsg(url){
                    $.getJSON(url, function (data,text,jqXHR){
                        if (data && data.status=='success') {

                            var board = data.board;
                            var turnnum = board.turn_player;                            
                            board = board.board;
                            
                            if(usrnum == turnnum){
                                $('#move').html("You Move");
                            }
                            
                            else{
                                $('#move').html(otherUser+" Move");
                            }
                                                       
                            var txt = "";
                            match_state = data.match_state;
                            
                            if(data.match_state == 1){
                                txt = "Active";
                            }

                            //player1 win
                            else if (data.match_state == 2){                 
                                $("#move").html("Game Over");
                                if (usrnum == 1){
                                    txt= "Win";
                                	alert("Congratulation!!! You win!!!");
                                }

                                else{
                                    txt = "Lose";
                                    alert("Sorry you lose.");
                                }
                                
                            }
                            
                            //player1 win
                            else if (data.match_state == 3){
                                $("#move").html("Game Over");                              
                                if (usrnum == 2){
                                    txt= "Win";
                                	alert("Congratulation!!! You win!!!");
                                }
                                else{        
                                    txt = "Lose";
                                    alert("Sorry you lose.");
                                }
                                
                            }
                            
                            else if(data.match_state == 4){
                                txt = "Tie";
                                $("#move").html("Game Over");
                                alert("Tie with your opponent");
                            }

                            $("#wstatus").html("status: "+ txt);
                            
                            for(var r = 0; r <= 6; r++){     
                                for(var c = 0; c <= 7; c++){
                                    pla = $(".column#"+c+" > .plate").filter("#"+r);
                                    if(pla.attr("user") != board[r][c]){
                                        pla.attr("user", board[r][c]);     
                                      	//pla.effect("pulsate");
                                        pla.effect("bounce");
                                    
                                    }
                                }
                            }

                    }

                });
                }
                

                $(document).ready(function(){

                    for(var r = 0; r<7; r++){
                        $("#gamearea").append('<div class="column" id='+r+'></div>')
                    }
                    for(var c = 0; c < 6; c++){
                            $(".column").append('<div class="plate" id ="'+c+'" user="0"></div>')
                        }
                    $(".column").click(function(){
                        if (match_state == 1){
                            var url = "<?php echo base_url(); ?>index.php/board/postMsg";
                            $.post(url,"msg="+this.id, function (data){
                                if (data) {
                                    data = $.parseJSON(data);
                                    if (data.status=='failure'){                                                        
                                        $("#move").html(data.message);
                                    }
                                }
                            });
                        }
                        
                    })
                    
                });
                
		$(function(){
			$('body').everyTime(2000,function(){
					if (status == 'waiting') {
                        $.getJSON('<?php echo base_url(); ?>index.php/arcade/checkInvitation',function(data, text, jqZHR){
						if (data && data.status=='rejected') {
							alert("Sorry, your invitation to play was declined!");
							window.location.href = '<?php echo base_url(); ?>index.php/arcade/index';
						}
						
						if (data && data.status=='accepted') {
							status = 'playing';
							$('#status').html('Playing with ' + otherUser);
						}
								
						});
					}
					var url = "<?php echo base_url(); ?>index.php/board/getMsg";
                    if (match_state == 1){
                        getMsg(url);
                    }
					
			});
	
		});
	</script>
	</head> 
	<body>  
	<h1>Game Area</h1>

	<div>
	Hello <?php echo $user->fullName();
		echo "<br/>";
        echo anchor('account/logout','Logout'); ?>  
	</div>
    
    <div class="match">
            <?php $color = ($usrnum == 2) ? 'red': 'yellow';
            ?>

            <?php echo "<p id='color'>Your color is " . $color . ". </p>" ?>
                        
			<div id='move'></div>
            <p id='status'> 
            <?php 
                if ($status == "playing") {echo "Playing with " . $otherUser->fullName();}
                else {echo "Wating for " . $otherUser->fullName();}
            ?>
            </p>
            <div id="wstatus"> </div>
        </div>   
        <br/>
        <div id="gamearea"></div>
        <p id="board"></p>
		
    </body>

</html>

