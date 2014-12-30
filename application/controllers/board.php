<?php

class Board extends CI_Controller {
     
    function __construct() {
    		// Call the Controller constructor
	    	parent::__construct();
	    	session_start();
    } 
          
    public function _remap($method, $params = array()) {
	    	// enforce access control to protected functions	
    		
    		if (!isset($_SESSION['user']))
   			redirect('account/loginForm', 'refresh'); //Then we redirect to the index page again
 	    	
	    	return call_user_func_array(array($this, $method), $params);
    }
    
    
    function index() {
		$user = $_SESSION['user'];
    		    	
	    	$this->load->model('user_model');
	    	$this->load->model('invite_model');
	    	$this->load->model('match_model');
	    	
	    	$user = $this->user_model->get($user->login);

	    	$invite = $this->invite_model->get($user->invite_id);
	    	
	    	if ($user->user_status_id == User::WAITING) {
	    		$invite = $this->invite_model->get($user->invite_id);
	    		$otherUser = $this->user_model->getFromId($invite->user2_id);
                //player number
                $usrnum = 2;
	    	}
	    	
	    	else if ($user->user_status_id == User::PLAYING) {	
	    		$match = $this->match_model->get($user->match_id);
	    		
	    		if ($match->user1_id == $user->id){
	    			$otherUser = $this->user_model->getFromId($match->user2_id);
                    //player number
                    $usrnum = 1;
                }
	    		
	    		
	    		else{
	    			$otherUser = $this->user_model->getFromId($match->user1_id);
                    
                    //player number
                    $usrnum = 2;
                }
	    	}
	    	
	    	$data['user']=$user;
            $data['usrnum']=$usrnum;
	    	$data['otherUser']=$otherUser;
	    	
	    	switch($user->user_status_id) {
	    		case User::PLAYING:	
	    			$data['status'] = 'playing';
	    			break;
	    		case User::WAITING:
	    			$data['status'] = 'waiting';
	    			break;
	    	}
	    	
		$this->load->view('match/board',$data);
    }

 	function postMsg() {
 		$this->load->library('form_validation');
 		$this->form_validation->set_rules('msg', 'Message', 'required');
 		
 		if ($this->form_validation->run() == TRUE) {
 			$this->load->model('user_model');
 			$this->load->model('match_model');

 			$user = $_SESSION['user'];
 			 
 			$user = $this->user_model->getExclusive($user->login);
 			if ($user->user_status_id != User::PLAYING) {	
				$errormsg="Not in PLAYING state";
 				//goto error;
 			}
 			
 			$match = $this->match_model->get($user->match_id);						
 			$msg = $this->input->post('msg'); 			
 			$usernum = ($match->user1_id == $user->id) ? 1 : 2;
 			$board = json_decode($match->board_state, true);
            
            
            //Does not actived
            if ($match->match_status_id != 1){
                $errormsg="This match is not active. ";
                //goto error;
            }
            
            //not the player turn
            if ((int)$board["turn_player"] != $usernum){
                $errormsg = "Your opponent move!";
                goto error;
            }

            //put current position
            if($this->move($match->id, (int)$msg, $board["board"], $usernum)){
                echo json_encode(array('status'=>'success','message'=>""));
            }
            
            //// the column is full
            // else{
            //     $errormsg = "You can't put here.";
            //     goto error;
            // }
            			
 			return;
 		}
		
 		$errormsg="Missing argument";
 		
		error:
			echo json_encode(array('status'=>'failure','message'=>$errormsg));
 	}
 
	
	function getMsg() {
 		$this->load->model('user_model');
 		$this->load->model('match_model');
 			
 		$user = $_SESSION['user'];
 		 
 		$user = $this->user_model->get($user->login);
 		if ($user->user_status_id != User::PLAYING) {	
 			$errormsg="Not in PLAYING state";
 			//goto error;
 		}
 		
 		// start transactional mode  
 		$this->db->trans_begin();
 			
 		$match = $this->match_model->getExclusive($user->match_id);			
 			
 		if ($match->user1_id == $user->id) {
			$msg = $match->u2_msg;
 			$this->match_model->updateMsgU2($match->id,"");
 		}
 		else {
 			$msg = $match->u1_msg;
 			$this->match_model->updateMsgU1($match->id,"");
 		}
        

        $board = json_decode($match->board_state);
        

        $match_state = $match->match_status_id;
 		

 		if ($this->db->trans_status() === FALSE) {
 			$errormsg = "Transaction error";
 			goto transactionerror;
 		}
 		
 		// if all went well commit changes
 		$this->db->trans_commit();
 		
 		echo json_encode(array('status'=>'success','message'=>$msg,'match_state'=>$match_state,'board'=>$board));
        
        $board_copy = json_decode($match->board_state, true);
        
		return;
		
		transactionerror:
		$this->db->trans_rollback();
		
		error:
		echo json_encode(array('status'=>'failure','message'=>$errormsg));
 	}


 	function move($id, $column, $board, $usernum){
        $this->load->model('match_model');
        
        //drop down
        for ($row=5; $row>=0; $row--){
            if ($board[$row][$column] == 0){
               	
               	$board[$row][$column] = $usernum;
                      
                $nextuser = ($usernum == 1) ? 2 : 1;
                  
                $this->match_model->updateBoard($id, array("board"=>$board, "turn_player"=>$nextuser));
                

                if ($this->win($id, $board, $row, $column)){
   
	                $status = ($usernum == 1) ? Match::U1WON : Match::U2WON;
                    
                    $this->match_model->updateStatus($id, $status);
                }

                elseif($this->tie($id, $board)){
                    $this->match_model->updateStatus($id, 4);
                }
                
                return TRUE;
            }
        }
        return FALSE;
    }

    
    //tie
    function tie($id, $board){
        foreach($board as $r)
        {
            foreach($r as $p)
            {
                if ($p == 0)
                {
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

    //horizontal Win
    function hWin($id, $board, $row, $column){
        
    	//put current position
        $posi = $board[$row][$column];
        $count = 0;
        for ($c=0; $c<=6; $c++){
            if($board[$row][$c] == $posi){
                $count++;
            }
            else{
                $count = 0;
            }
            
            if($count >= 4){
                return TRUE;
            }
        }

        return FALSE;
	}
	
	//vertical win 
	function vWin($id, $board, $row, $column){
        $posi = $board[$row][$column];
        $count = 0;
        for ($r = 0; $r <= 5; $r ++){
            if($board[$r][$column] == $posi){
                $count++;
            }else{
                $count=0;
            }
            
            if($count>=4){
                return TRUE;
            }
        }

        return FALSE;
    }
    
    //direct right win 
    function rWin($id, $board, $row, $column){    
        
    	$posi = $board[$row][$column];

        $min = min($row, $column);
        
        $r = $row - $min;
        $c = $column - $min;        
       	
       	$count = 0;
        while($c<=6 && $r<=6){
            
            if($board[$r][$c] == $posi){
                $count++;
            }
            else{
                $count=0;
            }
            
            if($count>=4){
                return TRUE;
            }
            
            $c++;
            $r++;
        }
        return FALSE;
 	}
    
    //direct left win 
 	function lWin($id, $board, $row, $column){  
        $posi = $board[$row][$column];

        $min = min(5-$row, $column);
        
        $r = $row + $min;
        $c = $column - $min;     
        
        $count = 0;
        while($r>=0 && $c<=6){
            if($board[$r][$c] == $posi){
                $count++;
            }
            else
            {
                $count=0;
            }
            
            if($count>=4){
                return TRUE;
            }
            
            $c++;
            $r--;
        
        }
        return FALSE;
    }

    
    function win($id, $board, $row, $column) {
    	return $this->vWin($id, $board, $row, $column) ||
               $this->hWin($id, $board, $row, $column) ||
               $this->rWin($id, $board, $row, $column) ||
               $this->lWin($id, $board, $row, $column);
    }
 	
 }
 ?>

