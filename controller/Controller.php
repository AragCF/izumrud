<?
 include_once("model/Model.php");          // connect to the Model part of our MVC pattern
 include_once("model/Settings.php");       // connect to the Settings 
 include_once("lessc.inc.php");            // the lesscss server-side compiler
// include_once("getxls.php");             // the MS Excel simple export class
 include_once("StringForge.1.1.php");      // the string processing class
 include_once("Template.1.88.php");             // add Template class
 include_once("Session.1.2.php");
 
 class Controller {                        // the Controller class
  public $model;                           // used to handle the link to the Model
  public $settings;                        // used to handle the link to the Settings (settings.ini in an object representation)
  public $StringForge;
  public $viewroot;
  public $themeinfo;
  
  public function __construct() {          // the constructor function
   $this->settings = Settings::init();     // load settings file
   new SessionSaveHandler(getrootdirsrv().$this->settings->sessionsavepath);
   
   date_default_timezone_set($this->settings->timezone);       // set the default timezone. may be we need to customize it for particular user
   $this->model       = new Model($this->settings);            // instantiate the Model class
   $this->less        = new lessc;
   $this->StringForge = new StringForge($this->settings);
  }
  
  public function run() {                                                      // the main sub in our application
   if(!isset($_SESSION)) session_start();
   
   $action = getvariablereq('action');          // get action from the request
   $data   = getvariablereq('data'  );          // get JSON data from the request
   
   $data = str_replace('\"','"',$data);            // fix some escaped paths (if any)
   
   if ($data) {
    $json=json_decode($data);                      // parse Json data came from AJAX (if any)
   }
   
   if (sizeof($_FILES)>0) {                        // file upload
//    echo "Fileupload...";
    
//    ajax_echo_r($_FILES);
    
    $file = $_FILES['fileupload'];
//    ajax_echo_r($file);
    mkdirr($this->settings->temppath);
    
    switch ($action) {
     case ('balance'):
      $fname = $this->settings->temppath.'/'.session_id().".xls";
      move_uploaded_file($file['tmp_name'], $fname);
      
      $this->model->loadBalance($fname);
      
     break;
    }
    
    if ($file['error']==0) {
     echo localize("<en>File uploaded successfully</en><ru>Файл загружен успешно</ru>");
    } else {
     echo localize("<en>Upload error</en><ru>Ошибка при загрузке файла</ru>");
    }
   } else {                                               // no file upload
    $userid     = getsecurevariable('userid'     ,0);
//    echo $userid;
    
//    $hue        = getvariable       ('hue'       );
    $hue                = getsecurevariable ('hue'            ,-1);
    $theme              = getsecurevariable ('theme'          ,-1);
    $SmoothAnimation    = getsecurevariable ('SmoothAnimation',-1);
    $userdetails = $this->model->getUserDetails($userid);          
    if (!is_dir("view/".$theme."/")) {
     $theme=$this->settings->defaulttheme;
    }
    if ((int)$SmoothAnimation==-1) {                                            // if it's not stored in session
     if ($userid>-1) {                                                             // if the user is logged
      $SmoothAnimation = $userdetails->SmoothAnimation;                         // get hue value from user details
     } else {                                                                   // if the user is came for the first time
      $SmoothAnimation = 0;                                                     // set the default value
     }
    }
    
    $userDetails = new stdClass();
    $this->model->setUserDetails($userDetails);
    
    $this->viewroot="view/".$theme;
    setsecurevariable("viewroot",$this->viewroot);
    $this->imgfolder = $this->viewroot."/img";
    
    if (file_exists($this->viewroot."theme.php")) {
     $this->themeinfo = parse_ini_file($this->viewroot."theme.php");
    }
    
    $userstate = $this->model->isUserAuth($userid);                             // check user's session and login state
    if ($action) {                                                             // start the processing of ajax commands
     header("Pragma:        no-cache");
     header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
     header("Expires:       0");
     
     if (($userid>-1) || ($action=='dologin') || ($action=='api') || ($action=='ownlogin')) {
      switch ($action) {
       case ("savefield"):
       case ("saveField"):
        $this->model->saveField($json);
       break;
       case ("dologin"):                                             // used to login the user
        $is_auth = $this->model->userAuth($json);
        if ($is_auth) {
         setsecurevariable('userid',$is_auth->ID);
         setsecurevariable('tmpcnt',0           );
  //       $tmp = new Template($this->viewroot, 'ajax_nouser_logged.htt');   // load JS user successful login template
         ajax_echo ("##refresh##");
        } else {
         $tmp = new Template($this->viewroot, 'ajax_nouser_error.htt');      // load JS user login error template
         $tmp->fill('%imgfolder%'          , $this->imgfolder                    );
         ajax_echo($tmp->output());
        }
       break;
       case ("dologout"):                                                       // used to log out
        setsecurevariable(             "hue", "-1" );                           // set to default (abstracted)
        setsecurevariable(           "theme", $this->settings->defaulttheme );  // set to default (abstracted)
        setsecurevariable( "SmoothAnimation", "1"  );                           // enabled by default
        setsecurevariable(          "userid", "-1" );
        $userid = -1;
  //       ajax_echo_r($_SESSION);
        ajax_echo ("##refresh##");
       break;
       case ("setUserParameter"):                                               // used to save user's setting
        setsecurevariable($json->name, $json->value);
 //        $userid = 0;
 //        ajax_echo_r($_SESSION);
        if (($json->name=='hue') || ($json->name=='theme')) {
         ajax_echo ("##refresh##");
        }
       break;
       
       case ("newpartner"):                                   // used to login the user
        $is_sent = (trim($json->name)!='') && (trim($json->email)!='') && (trim($json->comments)!='');
        if ($is_sent) {
         $is_sent = sendmail("Email from ".$json->name,"Bricks Pro partnership request: <br>\n".$json->comments);
        }
        if ($is_sent) {
         $tmp = new Template($this->viewroot, 'ajax_new_partner.htt');            // load JS success template
        } else {
         $tmp = new Template($this->viewroot, 'ajax_new_partner_error.htt');      // load JS error template
        }
        $tmp->fill('%imgfolder%'  ,$this->imgfolder );
        ajax_echo($tmp->output());
       break;
       
       case ("showgallery"):
 //        ajax_echo_r($json);
        if ($json->operation=='download') {
         $ret = $this->model->getGalleryItem($json);
        } else {
         if ($json->operation=='delete') {
          $ret = $this->model->deleteGalleryItem($json);
         }
         $gal = $this->getGallery($json);
         $ret = $gal->compiled;
        }
        ajax_echo ($ret);
       break;
       
       
       case ('postevent'):
        $ret=$this->model->addEvent($json);
 //       ajax_echo_r ($ret->rowsAffected);
        ajax_echo_r($json);
       break;
       
       case ('getnewevents'):
        $events = $this->model->getNewEvents($json);
        
        echo json_encode($events);
       break;
       
       case ('processxls'):
        $xls = $this->model->loadXls(0);
        
        setCache('settings',$json);
        
        if ($xls) {
         $data = $this->model->processXls($xls, $json);
        } else {
         $data = "";
        }
        ajax_echo_r($data);
        
       break;
       
       case ('showtable'):
        $json->tablename = $json->go;
        
        $thisdebug = 0;
        if ($thisdebug) $mtime = microtime(true);
        switch ($json->go) {
         case ('news'):
          $params = new stdClass;
          $params->listid=-1;
          $table = $this->model->getTasks($params);
         break;
         case ('users'):
          if ($userdetails->GroupID!=1) $id = $userdetails->ID;
          $table[1] = $this->model->getUsers($json, $id);
         break;
         case ('dashboard'):
          // see case sections for each dashboard subpage
         break;
         case ('objects'):
          $table = $this->model->getObjects($json);
         break;
         case ('customers'):
          $table = $this->model->getCustomers($json);
         break;
         case ('happiness'):
          $table = $this->model->getHappiness($json);
         break;
         case ('system'):
         break;
         default:
          $table = $this->model->getTable($json);
         break;
        }
        
//        ajax_echo_r($table);
//        ajax_echo_r($json);
        
        if ($json->isexport) {
         $tmp = new Template($this->viewroot, 'output_list.htt');                                      // load common parent template
         $block_item = new Template($this->viewroot, $tmp->returnloop('block_item'));
         
         $thislist = "";
         if (sizeof($table[1])) {
          foreach ($table[1] as $item) {
           $block_item->reload();
           $block_item->fill("%Item%"           , $item->Item     );
           $block_item->fill("%Name%"           , $item->Name     );
           $thislist.=$block_item->output();
          }
         }
         
         $tmp->fillloop("block_item"           , $thislist     );
         
         $tmp->fill("%numrows%",sizeof($table[1]));
         
         ajax_echo(sup($tmp->output()));
        } else {
//         ajax_echo_r ($json);
         if ($json->r_viewmode) {
          $tmp_src = new Template($this->viewroot, 'main_'.$json->go.'_'.$json->r_viewmode.'.htt');             // load common parent template
         } else {
          $tmp_src = new Template($this->viewroot, 'main_'.$json->go.'_'.$json->r_cutby.'.htt');                                   // load common parent template
          if (!$tmp_src->is_template) {
           $tmp_src = new Template($this->viewroot, 'main_'.$json->go.'.htt');                                   // load common parent template
          }
         }
         $tmp_src->fill(  '%imgfolder%' , $this->imgfolder                    );
         
//         ajax_echo_r ($json);
         switch ($json->go) {
          case ('dashboard'):
//           $tmp_src->loadloop('block_dashboard');
           
  //         $cutBy = "customersources";
           $cutBy   = $json->r_cutby;
           $caption = $json->r_cutby_caption;
           
           $tmp_src->fill('%caption%', $caption);
           
           switch ($json->r_cutby) {
            case ('common'):
             $t = $this->model->getStats('common','', $json->s_UserID);
             
             $vars = $tmp_src->getVariables();
//             ajax_echo_r ($t);
             foreach ($vars as $var) {
              if (is_numeric($item->$var)) {
               $tmp_src->fill(        '%'.$var.'%', format($t->$var, "#.#")   );
              } else {
               $tmp_src->fill(        '%'.$var.'%',        $t->$var           );
              }
             }
             
            break;
            case ('monthly'):
             $list = $this->model->getMoneyStats($json->r_cutby);
//             ajax_echo_r ($list);
             
             $loop = new Template($this->viewroot, $tmp_src->returnloop('loop_row'));
             
             $vars = $loop->getVariables();
//             ajax_echo_r ($t);
             foreach ($list->data as $t) {
              $loop->reload();
              foreach ($vars as $var) {
               if (is_numeric($t->$var)) {
                $loop->fill(        '%'.$var.'%', format($t->$var, "#.#")   );
               } else {
                $loop->fill(        '%'.$var.'%',        $t->$var           );
               }
              }
              $loopstr .= $loop->output();
             }
             $tmp_src->fillloop('loop_row', $loopstr);
             
             
             $vars = $tmp_src->getVariables();
             foreach ($vars as $var) {
              if (is_numeric($list->$var)) {
               $tmp_src->fill(        '%'.$var.'%', format($list->$var, "#.#")   );
              } else {
               $tmp_src->fill(        '%'.$var.'%',        $list->$var           );
              }
             }
             
            break;
            case ('deposits'):
             $list = $this->model->getDepositsStats($json);
//             ajax_echo_r ($list);
             
             foreach (array('objects', 'customers') as $tablename) {
              $c = 0;
              $loop = new Template($this->viewroot, $tmp_src->returnloop('loop_table_row_'.$tablename));
              $loopstr = "";
              
              if ($list->$tablename) {
               $vars = $loop->getVariables();
  //             ajax_echo_r ($t);
               foreach ($list->$tablename as $t) {
                $loop->reload();
                $loop->fill("%c%",  $c%2);
                foreach ($vars as $var) {
                 if (is_numeric($t->$var)) {
                  $loop->fill(        '%'.$var.'%', format($t->$var, "#.#")   );
                 } else {
                  $loop->fill(        '%'.$var.'%',        $t->$var           );
                 }
                }
                $loopstr .= $loop->output();
                $c++;
               }
              } else {
               $block_norows = new Template($this->viewroot, $tmp_src->returnloop('block_norows'));
               $loopstr = $block_norows->output();
              }
              $tmp_src->fillloop('loop_table_row_'.$tablename, $loopstr);
             }
             
             /*
             $vars = $tmp_src->getVariables();
             foreach ($vars as $var) {
              if (is_numeric($list->$var)) {
               $tmp_src->fill(        '%'.$var.'%', format($list->$var, "#.#")   );
              } else {
               $tmp_src->fill(        '%'.$var.'%',        $list->$var           );
              }
             }
             */
             
            break;
            case ('handshakes'):
             $list = $this->model->getHandshakesStats($json);
//             ajax_echo_r ($list);
             
             foreach (array('objects', 'customers') as $tablename) {
              $c = 0;
              $loop = new Template($this->viewroot, $tmp_src->returnloop('loop_table_row_'.$tablename));
              $loopstr = "";
              
              if ($list->$tablename) {
               $vars = $loop->getVariables();
  //             ajax_echo_r ($t);
               foreach ($list->$tablename as $t) {
                $loop->reload();
                $loop->fill("%c%",  $c%2);
                foreach ($vars as $var) {
                 if (is_numeric($t->$var)) {
                  $loop->fill(        '%'.$var.'%', format($t->$var, "#.#")   );
                 } else {
                  $loop->fill(        '%'.$var.'%',        $t->$var           );
                 }
                }
                $loopstr .= $loop->output();
                $c++;
               }
              } else {
               $block_norows = new Template($this->viewroot, $tmp_src->returnloop('block_norows'));
               $loopstr = $block_norows->output();
              }
              $tmp_src->fillloop('loop_table_row_'.$tablename, $loopstr);
             }
             
             /*
             $vars = $tmp_src->getVariables();
             foreach ($vars as $var) {
              if (is_numeric($list->$var)) {
               $tmp_src->fill(        '%'.$var.'%', format($list->$var, "#.#")   );
              } else {
               $tmp_src->fill(        '%'.$var.'%',        $list->$var           );
              }
             }
             */
             
            break;
            case ('userseff'):
             $loop = new Template($this->viewroot, $tmp_src->returnloop('loop'));
             $t = $this->model->getStatsUsersEff($json);
//             ajax_echo_r ($t);
             $vars = $loop->getVariables();
             $loopstr = "";
             foreach ($t as $k=>$item) {
              $loop->reload();
              $loop->fill('%total%', $k);
              foreach ($vars as $var) {
               if ($var) {
                if (is_numeric($item->$var)) {
                 $loop->fill(        '%'.$var.'%', format($item->$var, "#.#")   );
                } else {
                 $loop->fill(        '%'.$var.'%', $item->$var   );
                }
               }
              }
              $loopstr .= $loop->output();
             }
             $tmp_src->fillloop('loop', $loopstr);
            break;
            default:
             $loop = new Template($this->viewroot, $tmp_src->returnloop('loop_customersources'));
             $t = $this->model->getStats('customers', $cutBy, $json->s_UserID);
//             ajax_echo_r ($t);
             $vars = $loop->getVariables();
             $loopstr = "";
             foreach ($t as $k=>$item) {
              $loop->reload();
              $loop->fill('%total%', $k);
              foreach ($vars as $var) {
               if (is_numeric($item->$var)) {
                $loop->fill(        '%'.$var.'%', format($item->$var, "#.#")   );
               } else {
                $loop->fill(        '%'.$var.'%', $item->$var   );
               }
              }
              $loopstr .= $loop->output();
             }
             $tmp_src->fillloop('loop_customersources', $loopstr);
             
             $loop = new Template($this->viewroot, $tmp_src->returnloop('loop_objectsources'));
             $t = $this->model->getStats('objects', $cutBy, $json->s_UserID);
             $vars = $loop->getVariables();
             $loopstr = "";
             foreach ($t as $k=>$item) {
              $loop->reload();
              $loop->fill('%total%', $k);
              foreach ($vars as $var) {
               if (is_numeric($item->$var)) {
                $loop->fill(        '%'.$var.'%', format($item->$var, "#.#")   );
               } else {
                $loop->fill(        '%'.$var.'%', $item->$var   );
               }
              }
              $loopstr .= $loop->output();
             }
             $tmp_src->fillloop('loop_objectsources', $loopstr);
            break;
           }
           
           $tmp_src->fillloop(       "block_no","" );
           $tmp_src->fillloop(   "block_norows","" );
           $tmp_src->fillloop(    "block_type1","" );
           $tmp_src->fillloop(    "block_type2","" );
           $tmp_src->fillloop(     "block_dir0","" );
           $tmp_src->fillloop(     "block_dir1","" );
          break;
          case ('news'):
           $looptmp = new Template($this->viewroot, $tmp_src->returnloop('loop_table'));
           $loop_table_row = new Template($this->viewroot, $tmp_src->returnloop('loop_table_row'));
           $vars = $loop_table_row->getVariables();
           
           $c = 0;
           
           $thistable = "";
           if (sizeof($table)) {
            $thisidcolumnname = $json->tablename."_ID";
            foreach ($table as $item) {
             $loop_table_row->reload();
             foreach ($vars as $vn) {
              if ($vn!=="Children") {
               $loop_table_row->fill("%".$vn."%", $item->$vn);
              }
             }
             
             $thistable.=$loop_table_row->output();
             $c++;
            }
           }
           $looptmp->fillloop('loop_table_row',$thistable);
           $tmp_src->fillloop("loop_table",$looptmp->output());
           
           $tmp_src->fill("%numrows%",sizeof($table));
           
          break;
          case ('money'):
           $looptmp        = new Template($this->viewroot, $tmp_src->returnloop('loop_table'));
           $loop_table_row = new Template($this->viewroot, $tmp_src->returnloop('loop_table_row'));
           $vars = $loop_table_row->getVariables();
           
           $block_type = array();
           $block_type[1]     = new Template($this->viewroot, $tmp_src->returnloop('block_type1'));
           $block_type[2]     = new Template($this->viewroot, $tmp_src->returnloop('block_type2'));
           
           $block_type[1] = $block_type[1]->output();
           $block_type[2] = $block_type[2]->output();
           
           $lastTypeID = -1;
           $c = 0;
//           ajax_echo_r ($table[1]);
           $thistable = "";
           if (sizeof($table)) {
            $thisidcolumnname = $json->tablename."_ID";
            foreach ($table[1] as $item) {
             if ($lastTypeID!=$item->TypeID) {
              $lastTypeID = $item->TypeID;
              $c = 1;
             }
             
             $loop_table_row->reload();
             $loop_table_row->fill( "%TypePicture%", $block_type[$item->TypeID]);
             $loop_table_row->fill("%c%",  $c%2+($item->TypeID-1)*2);
             foreach ($vars as $vn) {
              $loop_table_row->fill("%".$vn."%", $item->$vn);
             }
             
             $thistable.=$loop_table_row->output();
             $c++;
            }
           }
           $looptmp->fillloop('loop_table_row',$thistable);
           $tmp_src->fillloop("loop_table",$looptmp->output());
           
           $tmp_src->fill("%numrows%",sizeof($table[1]));
           
           $tmp_src->fillloop(       "block_no","" );
           $tmp_src->fillloop(   "block_norows","" );
           $tmp_src->fillloop(    "block_type1","" );
           $tmp_src->fillloop(    "block_type2","" );
          break;
          case ('events'):
//           ajax_echo_r ($json);
           
           if (($json->go=='events') || ($json->go=='customers') || ($json->r_viewmode=='all') || ($json->r_viewmode=='current')) {
            $looptmp = clone $tmp_src;
            
            $looptmp->loadloop("loop_table");
            $tmp = clone $looptmp;
            
            if (sizeof($table[1])) {
             $block_no = clone $looptmp;
             $block_no->loadloop('block_no');
             $block_no=$block_no->output();
             
             $tmp->loadloop("checkbox_th");
             $checkbox_th=clone $tmp;
             $tmp = clone $looptmp;
             
             $tmp->loadloop("checkbox_td");
             $checkbox_td=clone $tmp;
             $tmp = clone $looptmp;
             
             $loop_table_row = clone $looptmp;                                  // load common parent template
             $loop_table_cell = clone $looptmp;                                 // load common parent template
             
             $looptmp->loadloop("loop_table_header");
             $loopdata = "";
             $loopdata.=$checkbox_th->output();
             
             if ($table[0]) {
              foreach ($table[0] as $k => $v ) {
               if ($v) {
                if ($v!='CustomerID') {
                 $looptmp->reload();
                 $looptmp->fill("%th%"       , $this->StringForge->prepare($v));
                 $loopdata.=$looptmp->output();
                }
               }
              }
             }
             
             $tmp->fillloop("loop_table_header"   ,$loopdata           );
             $loop_table_row->loadloop("loop_table_row");
             $loop_table_cell->loadloop("loop_table_cell");
             $emptyinfo = file_get_contents($this->viewroot.'/templates/emptyinfo.htt');
             $c = 0;
             
             $thistable = "";
             
             $thisidcolumnname = $json->tablename."_ID";
             foreach ($table[1] as $item) {
              $loop_table_row->reload();
              $thisrow   = "";
              $checkbox_td->reload();
              $checkbox_td->fill('%checked%' , 'checked'            );
              $checkbox_td->fill('%id%'      , $item->$thisidcolumnname );
              $thisrow.=$checkbox_td->output();
              foreach ($item as $k=>$v) {
               if ($k && ($k!='CustomerID')) {
                $loop_table_cell->reload();
                if ($k==$json->tablename."_AuxInfo") {
                 if (trim($v)=="") $v=$emptyinfo;
                }
                $loop_table_cell->fill("%celldata%"     , (((int)$v==0) && ((string)(int)$v==$v))?$block_no:($v));
                $loop_table_cell->fill("%k%"     , $k);
                $thisrow.=$loop_table_cell->output();
               }
              }
              $loop_table_row->fillloop('loop_table_cell',$thisrow);
              $loop_table_row->fill("%c%",  $c%2);
              
              $loop_table_row->fill("%id%"           , $item->ID             );
              $loop_table_row->fill("%tablename%"    , $json->tablename      );
              
              $thistable.=$loop_table_row->output();
              $c++;
             }
             $tmp->fillloop('loop_table_row',$thistable);
             $tmp->fillloop("checkbox_th","");
             $tmp->fillloop("checkbox_td","");
             $tmp_src->fillloop("block_norows","");
             
             
             $tmp_src->fillloop("loop_table",$tmp->output());
            } else {
             $block_norows = clone $looptmp;
             $block_norows->loadloop('block_norows');
             $block_norows=$block_norows->output();
             
             $tmp_src->fillloop("loop_table",$block_norows);
            }
            
            $tmp_src->fill("%numrows%",sizeof($table[1]));
           } else {
            switch ($json->r_viewmode=='monthly') {
             case ('monthly'):
              $loop_month = clone $tmp_src;
              $loop_month->loadloop('loop_month');
              
              $loop_bytype = clone $tmp_src;
              $loop_bytype->loadloop('loop_bytype');
              
              $months = "";
              if (sizeof($table[1])) {
               $report=array();
               $prevmonth = -1;
               
               //ajax_echo_r($table[1][0]);
               $thismonth = new stdClass();
               
               $fakeitem = new stdClass();
               $fakeitem->Month=-2;
               $table[1][]=$fakeitem;
               
               foreach ($table[1] as $item) {
                if (($prevmonth!=$item->Month) && ($thismonth->month)) {
                 $days = cal_days_in_month(CAL_GREGORIAN, $thismonth->month, $thismonth->year);
                 
                 $thismonth->etm    = $etm;
                 $thismonth->itm    = $itm;
                 $thismonth->aetm   = sprintf("%01F", $etm/$days);
                 $thismonth->aitm   = sprintf("%01F", $itm/$days);
                 $thismonth->ietm   = $ietm;
                 $thismonth->eetm   = $eetm;
                 
                 arsort($bytype);
                 $thismonth->bytype = $bytype;
                 
                 $report[] = clone $thismonth;
                 
                 $etm    = 0;
                 $itm    = 0;
                 $ietm   = 0;
                 $eetm   = 0;
                 $bytype = array();
                }
                
   //             if ($item->Month==5) {
   //              ajax_echo_r($bytype);
   //             }
                
                $thismonth->year  = $item->Year  ;
                $thismonth->month = $item->Month ;
                
                $bytype[$item->Description] += $item->Value;
                
                If (($item->EZID == 1) And ($item->TypeID == 2)) {
                 $eetm += $item->Value;
                }
                If (($item->EZID == 2) And ($item->TypeID == 2)) {
                 $ietm += $item->Value;
                }
                
                If (($item->TypeID == 1) || ($item->TypeID == 3)) {                   // income
                 $itm += $item->Value;
                } else {                                                               // expenditure
                 $etm += $item->Value;
                }
                
                $prevmonth=$item->Month;
                
               }
               
               foreach ($report as $thismonth) {
                $loop_month->reload();
                $loop_month->fill( "%year%",$thismonth->year );
                $loop_month->fill("%month%",$thismonth->month);
                
                $loop_month->fill(  "%etm%",$thismonth->etm  );
                $loop_month->fill(  "%itm%",$thismonth->itm  );
                $loop_month->fill( "%aetm%",$thismonth->aetm );
                $loop_month->fill( "%aitm%",$thismonth->aitm );
                $loop_month->fill( "%eetm%",$thismonth->eetm );
                $loop_month->fill( "%ietm%",$thismonth->ietm );
                
                $bytype="";
                if (is_array($thismonth->bytype)) {
                 foreach ($thismonth->bytype as $k=>$v) {
                  $loop_bytype->reload();
                  
                  $loop_bytype->fill("%k%",$k);
                  $loop_bytype->fill("%v%",$v);
                  
                  $bytype.= $loop_bytype->output();
                 }
                }
                
                $loop_month->fillloop("loop_bytype",$bytype);
                $months.=$loop_month->output();
               }
              }
              $tmp_src->fillloop("loop_month",$months);
             break;
            }
           }
           
          break;
          case ('spiderman'):
           $looptmp = clone $tmp_src;
           $looptmp->loadloop('loop_item');
           $loop_item = "";
           $vars = $looptmp->getVariables();
           
           $parents[] = array();
           
  //         ajax_echo_r ($table[1]);
           if (sizeof($table[1])) {
            foreach ($table[1] as $item) {
             foreach ($vars as $vn) {
              if ($vn!=="Children") {
               $looptmp->fill("%".$vn."%", $item->$vn);
              }
             }
             if (!$parents[$item->ParentID]) $parents[$item->ParentID] = new stdClass();
             $parents[$item->ParentID]->Content.=$looptmp->output();
             $parents[$item->ParentID]->ID=$item->ID;
  //           $loop_item.=$looptmp->output();
             $looptmp->reload();
            }
           }
           
  //         ajax_echo_r ($parents);
           
           foreach($parents as $parent) {
            $parent->Content = str_replace("Children_".$parent->ID."_", $parents[$parent->ID]->Content, $parent->Content);
  //          ajax_echo_r ($parent->Content);
  //          echo "<hr>";
           }
           
           foreach ($table[1] as $item) {
            $parents[0]->Content = str_replace("Children_".$item->ID."_", "", $parents[0]->Content);
           }
           $loop_item=$parents[0]->Content;
           
           $tmp_src->fillloop('loop_item', $loop_item);
          break;
          
          case ('diary'):
           $block_no = new Template($this->viewroot, $tmp_src->returnloop('block_no'));
           $block_no=$block_no->output();
           
           $block_norows = new Template($this->viewroot, $tmp_src->returnloop('block_norows'));
           $block_norows=$block_norows->output();
           
//           $looptmp->loadloop("loop_table");
//           $tmp = clone $looptmp;
           
           $checkbox_th    = new Template($this->viewroot, $tmp_src->returnloop('checkbox_th'));
           $checkbox_td    = new Template($this->viewroot, $tmp_src->returnloop('checkbox_td'));
           $loop_table_row = new Template($this->viewroot, $tmp_src->returnloop('loop_table_row'));
           
           $loopdata = "";
           $loopdata.=$checkbox_th->output();
           
           $cols = array();
           $cols[] = 'ID';
           $cols[] = 'DateAdded';
           $cols[] = 'DateTarget';
           $cols[] = 'Description';
           
           $emptyinfo = file_get_contents($this->viewroot.'/templates/emptyinfo.htt');
           $c = 0;
           
           $thistable = "";
           if (sizeof($table[1])) {
            $thisidcolumnname = $json->tablename."_ID";
    //          echo $thisidcolumnname."<br>";
            foreach ($table[1] as $item) {
             $loop_table_row->reload();
             $thisrow   = "";
             $checkbox_td->reload();
             $checkbox_td->fill('%checked%' , 'checked'            );
             $checkbox_td->fill('%id%'      , $item->$thisidcolumnname );
             $thisrow.=$checkbox_td->output();
             
             $loop_table_row->fill(        "%ID%", $item->ID);
             
             foreach ($cols as $cv) {
              $k=$cv;
              $v = $item->$k;
              if ($k && ($k!='CustomerID')) {
               if ($k==$json->tablename."_AuxInfo") {
                if (trim($v)=="") $v=$emptyinfo;
               }
               
               $loop_table_row->fill(  "%".$k."%", (((int)$v==0) && ((string)(int)$v==$v) && ($k!='Cost'))?$block_no:($v));
              }
             }
             $loop_table_row->fillloop('loop_table_cell',$thisrow);
             $loop_table_row->fill("%c%",  ($c%2) + ((date_timestamp_get(date_create($item->DateTarget))<date_timestamp_get(date_create()) )?0:2) );
             
//             echo (date_timestamp_get(date_create($item->DateTarget))."-".date_timestamp_get(date_create())."<br>");
             
             $loop_table_row->fill("%id%"           , $item->ID             );
             $loop_table_row->fill("%tablename%"    , $json->tablename      );
             
             $thistable.=$loop_table_row->output();
             $c++;
            }
           } else {
            /*
            $thisrow   = "";
            $thisrow.=$block_norows;
            $loop_table_row->fillloop('loop_table_cell',$thisrow);
            $loop_table_row->fill("%c%",  $c%2);
            $thistable.=$loop_table_row->output();
            */
            $tmp_src->fillloop('loop_table', $block_norows);
           }
           
           $tmp_src->removeloop('loop_table');
           
           $tmp_src->fillloop('loop_table_row',$thistable);
           $tmp_src->fillloop(     "block_no","" );
           $tmp_src->fillloop( "block_norows","" );
           
           $tmp_src->fill("%numrows%",sizeof($table[1]));          
           
          break;
          case ('objects'):
           $tmp_src = $this->fillObjects($tmp_src, $table, $json, $userdetails);
           
          break;
          case ('customers'):
           $tmp_src = $this->fillCustomers($tmp_src, $table, $json, $userdetails);
           
          break;
          case ('happiness'):
           if ($json->r_viewmode=='objects') {
            $tmp_src_subitems = new Template($this->viewroot, 'main_customers.htt');                                   // load common parent template
            $tmp_src = $this->fillObjects($tmp_src, $table, $json, $userdetails, $tmp_src_subitems);
           } else {
            $tmp_src_subitems = new Template($this->viewroot, 'main_objects.htt');                                   // load common parent template
            $tmp_src = $this->fillCustomers($tmp_src, $table, $json, $userdetails, $tmp_src_subitems);
           }
           if ($json->r_viewmode=='objects') {
            $objects = $this->fillCustomers(clone $tmp_src_subitems, $item->subitems, $json, $userdetails);
           } else {
            $objects = $this->fillObjects  (clone $tmp_src_subitems, $item->subitems, $json, $userdetails);
           }
           
          break;
          case ('users'):
//           echo $id;
           if ($id) {
            $tmp_src->fillloop('block_delete', '');
           } else {
            $tmp_src->removeloop('block_delete');
           }
           
           $block_no       = new Template($this->viewroot, $tmp_src->returnloop('block_no'));
           $block_no=$block_no->output();
           
           $block_norows   = new Template($this->viewroot, $tmp_src->returnloop('block_norows'));
           $block_norows=$block_norows->output();
           
           $checkbox_th    = new Template($this->viewroot, $tmp_src->returnloop('checkbox_th'));
           $checkbox_td    = new Template($this->viewroot, $tmp_src->returnloop('checkbox_td'));
           
           $loop_table_row = new Template($this->viewroot, $tmp_src->returnloop('loop_table_row'));
           
           $block_dir = array();
           $block_dir[0]     = new Template($this->viewroot, $tmp_src->returnloop('block_dir0'));
           $block_dir[1]     = new Template($this->viewroot, $tmp_src->returnloop('block_dir1'));
           
           $block_dir[0] = $block_dir[0]->output();
           $block_dir[1] = $block_dir[1]->output();
           
           $loopdata = "";
           $loopdata.=$checkbox_th->output();
           
           $cols = $loop_table_row->getVariables();
//           ajax_echo_r ($cols);
           
           $emptyinfo = file_get_contents($this->viewroot.'/templates/emptyinfo.htt');
           $c = 0;
           
           $thistable = "";
           if (sizeof($table[1])) {
            $thisidcolumnname = $json->tablename."_ID";
    //          echo $thisidcolumnname."<br>";
            foreach ($table[1] as $item) {
             $loop_table_row->reload();
             $thisrow   = "";
             $checkbox_td->reload();
             $checkbox_td->fill('%checked%' , 'checked'            );
             $checkbox_td->fill('%id%'      , $item->$thisidcolumnname );
             $thisrow.=$checkbox_td->output();
             
             $loop_table_row->fill(           "%ID%", $item->ID);
             $loop_table_row->fill( "%calldiretion%", $block_dir[$item->DirectionID]);
//             $loop_table_row->fill("%c%",  ($c%2) + (((date_timestamp_get(date_create($item->LastAccess)) + 3600*72)>date_timestamp_get(date_create()) )?0:2) );
             $loop_table_row->fill("%c%",  $c%2);
             
             foreach ($cols as $cv) {
              $k=$cv;
              $v = $item->$k;
              if ($k && ($k!='CustomerID')) {
               if ($k==$json->tablename."_AuxInfo") {
                if (trim($v)=="") $v=$emptyinfo;
               }
               
               $loop_table_row->fill(  "%".$k."%", (((int)$v==0) && ((string)(int)$v==$v) && ($k!='Cost'))?$block_no:($v));
              }
             }
             $loop_table_row->fillloop('loop_table_cell',$thisrow);
             
//             echo (date_timestamp_get(date_create($item->DateTarget))."-".date_timestamp_get(date_create())."<br>");
             
             $loop_table_row->fill("%id%"           , $item->ID             );
             $loop_table_row->fill("%tablename%"    , $json->tablename      );
             
             $thistable.=$loop_table_row->output();
             $c++;
            }
           } else {
            /*
            $thisrow   = "";
            $thisrow.=$block_norows;
            $loop_table_row->fillloop('loop_table_cell',$thisrow);
            $loop_table_row->fill("%c%",  $c%2);
            $thistable.=$loop_table_row->output();
            */
            $tmp_src->fillloop('loop_table', $block_norows);
           }
           
           $tmp_src->removeloop('loop_table');
           
           $tmp_src->fillloop('loop_table_row',$thistable);
           
           $tmp_src->fillloop(     "block_no","" );
           $tmp_src->fillloop( "block_norows","" );
           $tmp_src->fillloop(   "block_dir0","" );
           $tmp_src->fillloop(   "block_dir1","" );
           
           
           $tmp_src->fill("%numrows%",sizeof($table[1]));          
           
          break;
         }
         
         $tmp_src->processfcb('');
         $tmp_src->fill(  '%imgfolder%' , $this->imgfolder                    );
         $tmp_src->fill(   '%viewroot%' , $this->viewroot                     );
         
         ajax_echo(sup($tmp_src->output()));
        }
       break;
       
       case ('showtasks'):
//        ajax_echo_r($json);
//        session_write_close();
        
        setsecurevariable('TaskListID', $json->flt_TaskListID);
        setsecurevariable('ProjectID', $json->flt_ProjectID);
        
        $loop = new Template($this->viewroot, 'main_tasks.htt');                                      // load common parent template
        $block_notasks = new Template($this->viewroot, $loop->returnloop('block_notasks'));
        
        $loop->loadloop("loop_task".$json->listid);
        
        $list = $this->model->getTasks($json);
 //       ajax_echo_r ($list);
        $loopdata = "";
        
        $vars = $loop->getVariables();
 //       ajax_echo_r($vars);
        
        if (sizeof($list)>0)  {
//         $i=0;
         foreach ($list as $ik=>$iv) {
//          ajax_echo_r ($iv);
//          echo $iv->ProjectShortTitle;
//          if (($i==0) && ($json->flt_TaskListID>0)) setsecurevariable('ShortTitle', $iv->ProjectShortTitle);
          
 //         $block_comments = $this->getComments($iv->ID);
 //         $loop->fill(  '%Comments%' , $block_comments                    );
          foreach ($vars as $vn) {
           if ($vn=='Description') {
            $loop->fill("%".$vn."%", ru_translate("".$iv->$vn));
           } else {
            $loop->fill("%".$vn."%", ($iv->$vn));
           }
          }
          
          if ($iv->StateID==1) {
           $loop->fillloop('block_paused','');
           $loop->removeloop('block_playing');
          } else {
           $loop->removeloop('block_paused');
           $loop->fillloop('block_playing','');
          }
          
          $loop->processfcb('');
          $loop->fill(  '%imgfolder%' , $this->imgfolder                    );
          $loopdata.=$loop->output();
          $loop->reload();
          
//          $i++;
         }
        } else {
         $loopdata.=$block_notasks->output();
         //$loopdata.=$block_notasks->output();
        }
        
        ajax_echo($loopdata);
        
        
        
        
        
        
        
        
        
 //       ajax_echo_r($buf);
        
       break;
       
       case ('generateresult'): 
 //       $block_link = new Template($this->viewroot, 'mainparent.htt');
 //       $block_link->loadloop('block_link');
        
 //       $block_link->fill('%href%', $this->model->generateOutput());
        
 //       echo localize($block_link->output());
        echo $this->model->generateOutput();
 //       echo ('test');
        
       break;
       
       case ("cleardatabase"):
        setCache('data', "");
       break;
       
       case ('clearColumn'):
        echo $this->model->clearColumn($json->id);
       break;
       
       
       
       
       case ("setListID"):
        $this->model->setListID($json);
       break;
       
       case ("addTask"):
        $json->AddedBy = $userid;
 //       echo $userid;
        $this->model->addTask($json);
       break;
       
       case ("deleteTask"):
        $this->model->deleteTask($json);
       break;
       
       case ("resumeTask"):
        $this->model->resumeTask($json);
       break;
       
       case ("pauseTask"):
        $this->model->pauseTask($json);
       break;
       
       
       
       
       case ('closeProject'):
        ajax_echo_r ($json);
        $ret = $this->model->closeProject($json);
        ajax_echo_r ($ret);
       break;
       
       case ('addProject'):
        $this->model->addProject($json, $userid);
       break;
       
       case ('deleteProject'):
        $this->model->deleteProject($json);
       break;
       
       case ('addComment'):
        $json->UserID = $userid;
        ajax_echo_r ($json);
        $ret = $this->model->addComment($json);
        ajax_echo_r ($ret);
        
       break;
       
       case ('getComments'):
 //       ajax_echo_r($json);
 //       ajax_echo_r (htmlentities($this->getComments($json)));
        ajax_echo (($this->getComments($json)));
        
       break;
       
       case ('deleteComment'):
 //       ajax_echo_r($json);
        $ret = $this->model->deleteComment($json);
 //       ajax_echo_r($ret);
        echo $this->getComments($ret);
        
       break;
       
       
       
       
       case ('addSpider'):
        $this->model->addSpider($json);
       break;
       
       case ('deleteSpider'):
        $this->model->deleteSpider($json);
       break;
       
       
       
       case ('api'):
        $method = getvariablereq('method');
        
        switch ($method) {
         case ('getCurrentProjects'):
          $json = new stdClass;
          $json->tablemode = 0;
          $json->isexport = 0;
          $json->go = "projects";
          $json->r_projectsview = "current";
          $json->tablename = "projects";
          $json->isapi = 1;
          
          $table = $this->model->getTable($json);
          
         break;
         default:
          echo "Unknown api method: ".$method;
         break;
        }
        
        echo json_encode($table);
        
       break;
       
       
       
       case ('loadUserPrivileges'):
        echo $this->getUserPrivileges($json);
       break;
       
       case ('addUserPrivilege'):
        $this->model->addUserPrivilege($json);
        echo $this->getUserPrivileges($json);
       break;
       
       case ('removeUserPrivilege'):
        $this->model->removeUserPrivilege($json);
        echo $this->getUserPrivileges($json);
       break;
       
       
       case ('selectChange'):
//        ajax_echo_r($json);
        $ret = new stdClass;
        $ret->debug = ajax_return_r($json);
        
        switch ($json->id) {
         case ('TypeID'):
          $leftcontent = new Template($this->viewroot, 'left_money.htt');                                      // load common parent template
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_groupids"));
          $list = $this->model->getExpenditureGroups($json->value);
//          ajax_echo_r ($list);
          $loopdata = $this->fillList($list, $loop, 0);
//          $leftcontent->fillloop('loop_groupids', $loopdata);
          $ret->GroupID = localize($loopdata);
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_placenames"));
          $list = $this->model->getPlaceNames($json->value);
//          ajax_echo_r ($list);
          $loopdata = $this->fillList($list, $loop, 0);
//          $leftcontent->fillloop('loop_placenames', $loopdata);
          $ret->PlaceName = localize($loopdata);
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_placetypes"));
          $list = $this->model->getPlaceTypes($json->value);
//          ajax_echo_r ($list);
          $loopdata = $this->fillList($list, $loop, 0);
//          $leftcontent->fillloop('loop_placetypes', $loopdata);
          $ret->PlaceType = localize($loopdata);
         break;
         
         case ('GroupID'):
          $leftcontent = new Template($this->viewroot, 'left_money.htt');                                      // load common parent template
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_placenames"));
          $list = $this->model->getPlaceNames(0, 0, $json->value);
//          ajax_echo_r ($list);
          $loopdata = $this->fillList($list, $loop, 0);
//          $leftcontent->fillloop('loop_placenames', $loopdata);
          $ret->PlaceName = localize($loopdata);
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_placetypes"));
          $list = $this->model->getPlaceTypes(0, 0, $json->value);
//          ajax_echo_r ($list);
          $loopdata = $this->fillList($list, $loop, 0);
//          $leftcontent->fillloop('loop_placetypes', $loopdata);
          $ret->PlaceType = localize($loopdata);
         break;
         
         case ('PlaceType'):
          $leftcontent = new Template($this->viewroot, 'left_money.htt');                                      // load common parent template
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_placenames"));
          $list = $this->model->getPlaceNames(0, $json->value);
//          ajax_echo_r ($list);
          $loopdata = $this->fillList($list, $loop, 0);
//          $leftcontent->fillloop('loop_placenames', $loopdata);
          $ret->PlaceName = localize($loopdata);
         break;
         
         case ('PlaceName'):
          $leftcontent = new Template($this->viewroot, 'left_money.htt');                                      // load common parent template
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_placetypes"));
          $list = $this->model->getPlaceTypes(0, $json->value);
//          ajax_echo_r ($list);
          $loopdata = $this->fillList($list, $loop, 0);
//          $leftcontent->fillloop('loop_placetypes', $loopdata);
          $ret->PlaceType = localize($loopdata);
         break;
         
         case ('CustomerTypeID'):
          $leftcontent = new Template($this->viewroot, 'left_objects.htt');                                      // load common parent template
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customersources"));
          $list = $this->model->getCustomerSources($json->value);
          $loopdata = $this->fillList($list, $loop, 0);
          $ret->SourceID = localize($loopdata);
          
          $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customersubtypes"));
          $list = $this->model->getCustomerSubtypes($json->value);
//          ajax_echo_r ($list);
          $loopdata = $this->fillList($list, $loop, 0);
          $ret->CustomerSubtypeID = localize($loopdata);
         break;
        }
        
        echo json_encode($ret);
       break;
       
       
       case ('newMoney'):
//        ajax_echo_r ($json);
        if ($json->ID) {
         $rec = $this->model->getMoney($json->ID);
        } else {
         $rec = new stdClass;
         $rec->TypeID=1;
        }
//        ajax_echo_r ($rec);
        
        $leftcontent = new Template($this->viewroot, "editor_money.htt");
//        $leftcontent->loadloop('block_popupdefault','');
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_typeids"));
        $list = $this->model->getList('moneyrecordtypes');
//        ajax_echo_r ($list);
//        ajax_echo_r ($rec);
        $loopdata = $this->fillList($list, $loop, $rec->TypeID);
        $leftcontent->fillloop('loop_typeids', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_groupids"));
        $list = $this->model->getExpenditureGroups($rec->TypeID);
//         ajax_echo_r ($list);
        $loopdata = $this->fillList($list, $loop, $rec->GroupID);
        $leftcontent->fillloop('loop_groupids', $loopdata);
        
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customersources_objects"));
        $list = $this->model->getCustomerSources(1);
//        ajax_echo_r ($list);
        $loopdata = $this->fillList($list, $loop, $rec->SourceID);
        $leftcontent->fillloop('loop_customersources_objects', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customersources_customers"));
        $list = $this->model->getCustomerSources(2);
//        ajax_echo_r ($list);
        $loopdata = $this->fillList($list, $loop, $rec->SourceID);
        $leftcontent->fillloop('loop_customersources_customers', $loopdata);
        
        
        /*
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_months"));
        $list = $this->model->getMonths('money','DateAdded');
        $loopdata = $this->fillList($list, $loop, 0);
        $leftcontent->fillloop('loop_months', $loopdata);
        */
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_objects"));
        $list = $this->model->getObjects(false, 1);
//        ajax_echo_r ($list);
        $loopdata = $this->fillList($list, $loop, $rec->ObjectID);
        $leftcontent->fillloop('loop_objects', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customers"));
        $list = $this->model->getCustomers();
//        ajax_echo_r ($list);
        $loopdata = $this->fillList($list, $loop, $rec->CustomerID);
        $leftcontent->fillloop('loop_customers', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_users"));
        $list = $this->model->getUsersLst();
        $loopdata = $this->fillList($list, $loop, $rec->UserID);
        $leftcontent->fillloop('loop_users', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_placenames"));
        $list = $this->model->getPlaceNames($rec->TypeID);
        $loopdata = $this->fillList($list, $loop, $rec->PlaceName, "PlaceName");
        $leftcontent->fillloop('loop_placenames', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_placetypes"));
        $list = $this->model->getPlaceTypes($rec->TypeID);
        $loopdata = $this->fillList($list, $loop, $rec->PlaceType, "PlaceType");
        $leftcontent->fillloop('loop_placetypes', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_accounts"));
        $list = $this->model->getListOrdered('accounts', 'ID');
        $loopdata = $this->fillList($list, $loop, $rec->AccountID);
        $leftcontent->fillloop('loop_accounts', $loopdata);
        
        
//        $leftcontent
        $vars = $leftcontent->getVariables();
        
        foreach ($vars as $var) {
         if ($var) {
          $leftcontent->fill(       "%".$var."%" , $rec->$var);
         }
        }
        
        $leftcontent->fill(  '%imgfolder%' , $this->imgfolder                    );
        $leftcontent->fill(   '%viewroot%' , $this->viewroot                     );
        $leftcontent->processfcb('');
        ajax_echo (localize($leftcontent->output()));
       break;
       
       case ('addMoney'):
        $ret = new stdClass;
//        if (date_create($json->DateAdded)) {
//         ajax_echo_r (date_create($json->DateAdded));
//         echo "test: ".(date_format(date_create($json->DateAdded), 'Y'));
//        }
        
//        return 1;
        if (floatval($json->Value)<=0) {
         $ret->message = $this->getMessage('addmoney_error_novalue');
         $ret->result = 0;
        } elseif ((date_create($json->DateAdded)) && ((int)date_format(date_create($json->DateAdded), 'Y')<1985)) {
         $ret->message = $this->getMessage('addmoney_error_nodate');
         $ret->result = 0;
        } else {
         if ($this->model->addMoney($json)) {
          $ret->message = $this->getMessage('addmoney_success');
          $ret->result = 1;
         } else {
          $ret->message = $this->getMessage('addmoney_error_unknown');
          $ret->result = 0;
         }
        }
        echo json_encode($ret);
        
       break;
       
       case ('deleteMoney'):
        $this->model->deleteMoney($json);
       break;
       
       
       
       
       case ('newDiary'):
//        ajax_echo_r ($json);
        if ($json->ID) {
         $rec = $this->model->getDiary($json->ID);
        } else {
         $rec = new stdClass;
         $rec->TypeID=1;
        }
        
//        ajax_echo_r ($rec);
        
        $leftcontent = new Template($this->viewroot, "left_diary.htt");
        $leftcontent->loadloop('block_popupdefault','');
        
        $vars = $leftcontent->getVariables();
        
        foreach ($vars as $var) {
         if ($var) {
          $leftcontent->fill(       "%".$var."%" , brtonl($rec->$var));
         }
        }
        
        $leftcontent->fill(  '%imgfolder%' , $this->imgfolder                    );
        $leftcontent->fill(   '%viewroot%' , $this->viewroot                     );
        $leftcontent->processfcb('');
        ajax_echo (localize($leftcontent->output()));
       break;
       
       case ('addDiary'):
        $ret = new stdClass;
        if (($json->Description)=='') {
         $ret->message = $this->getMessage('addmoney_error_novalue');
         $ret->result = 0;
        } elseif ((date_create($json->DateTarget)) && ((int)date_format(date_create($json->DateTarget), 'Y')<1985)) {
         $ret->message = $this->getMessage('addmoney_error_nodate');
         $ret->result = 0;
        } else {
         if ($this->model->addDiary($json)) {
          $ret->message = $this->getMessage('addmoney_success');
          $ret->result = 1;
         } else {
          $ret->message = $this->getMessage('addmoney_error_unknown');
          $ret->result = 0;
         }
        }
        echo json_encode($ret);
        
       break;
       
       case ('deleteDiary'):
        $this->model->deleteDiary($json);
       break;
       
       
       
       
       
       
       
       
       
       
       
       
       
       
       case ('newUser'):
        $leftcontent = new Template($this->viewroot, "left_users.htt");
        $leftcontent->loadloop('block_popupdefault','');
        
        if ($userdetails->GroupID==1) {
         $leftcontent->removeloop('block_adminonly2');
        } else {
         $leftcontent->fillloop('block_adminonly2','');
        }
        
        if ($json->ID) {
         $rec = $this->model->getUser($json->ID);
         $leftcontent->removeloop('block_editonly');
        } else {
         $rec = new stdClass;
         $rec->TypeID=1;
         $leftcontent->fillloop('block_editonly', '');
        }
        
        $leftcontent->fillloop('block_p','');
        
        $vars = $leftcontent->getVariables();
        
        foreach ($vars as $var) {
         if ($var) {
          $leftcontent->fill(       "%".$var."%" , brtonl($rec->$var));
         }
        }
        
        $leftcontent->processfcb('');
        $leftcontent->fill(  '%imgfolder%' , $this->imgfolder                    );
        $leftcontent->fill(   '%viewroot%' , $this->viewroot                     );
        ajax_echo (localize($leftcontent->output()));
       break;
       
       case ('addUser'):
        $ret = new stdClass;
        if (($json->Username=='') && ($userdetails->ID!=1)) {
         $ret->message = $this->getMessage('adduser_error_nousername');
         $ret->result = 0;
        } elseif (($json->Email=='') && ($userdetails->ID!=1)) {
         $ret->message = $this->getMessage('adduser_error_noemail');
         $ret->result = 0;
        } else {
         if ($this->model->addUser($json)) {
          $ret->message = $this->getMessage('addmoney_success');
          $ret->result = 1;
         } else {
          $ret->message = $this->getMessage('addmoney_error_unknown');
          $ret->result = 0;
         }
        }
        echo json_encode($ret);
       break;
       
       case ('deleteUser'):
        $this->model->deleteUser($json);
       break;
       
       case ('updatePassword'):
        $ret = new stdClass;
        if ($json->Password=='') {
         $ret->message = $this->getMessage('updatepassword_error_nopassword');
         $ret->result = 0;
        } else {
         if ($this->model->updatePassword($json)) {
          $ret->message = $this->getMessage('updatepassword_success');
          $ret->result = 1;
         } else {
          $ret->message = $this->getMessage('addmoney_error_unknown');
          $ret->result = 0;
         }
        }
        echo json_encode($ret);
       break;
       
       
       
       
       
       
       
       
       case ('cloneObject'):
       case ('newObject'):
        $leftcontent = new Template($this->viewroot, "editor_objects.htt");
        
        $rec = $this->model->getObject($json, $userdetails);
//        $rec_od = $this->model->getObjectDetails($rec->ID, $rec->MarketID);
        if ($action=='cloneObject') $rec->ID = '';
        
        if (($rec->UserID!=$userid) && ($userdetails->GroupID!=1)) {
         $leftcontent->fillloop  ('block_phone_editor','');
         $leftcontent->removeloop('block_phone_hidden');
         
         $leftcontent->fillloop  ('block_userselector','');
         $leftcontent->removeloop('block_nouserselector');
        } else {
         $leftcontent->removeloop('block_phone_editor');
         $leftcontent->fillloop  ('block_phone_hidden','');
         
         $leftcontent->removeloop('block_userselector');
         $leftcontent->fillloop  ('block_nouserselector','');
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_users"));
         $list = $this->model->getList('users');
         $loopdata = $this->fillList($list, $loop, $rec->UserID);
         $leftcontent->fillloop('loop_users', $loopdata);
        }
        
        $typepicture = array();
        $typepicture[1] = $leftcontent->returnloop('block_typepicture_1');
        $typepicture[2] = $leftcontent->returnloop('block_typepicture_2');
        $typepicture[3] = $leftcontent->returnloop('block_typepicture_3');
        $typepicture[4] = $leftcontent->returnloop('block_typepicture_4');
        $typepicture[5] = $leftcontent->returnloop('block_typepicture_5');
        $typepicture[6] = $leftcontent->returnloop('block_typepicture_6');
        
        $leftcontent->fillloop('block_typepicture_1', '');
        $leftcontent->fillloop('block_typepicture_2', '');
        $leftcontent->fillloop('block_typepicture_3', '');
        $leftcontent->fillloop('block_typepicture_4', '');
        $leftcontent->fillloop('block_typepicture_5', '');
        $leftcontent->fillloop('block_typepicture_6', '');
        
        
//        $leftcontent->loadloop('block_popupdefault','');
        
        /*
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customertypes"));
        $list = $this->model->getList('customertypes');
        $loopdata = $this->fillList($list, $loop, $rec->CustomerTypeID);
        $leftcontent->fillloop('loop_customertypes', $loopdata);
        */
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_methodsofpayment"));
        $list = $this->model->getMethodsOfPayment(2);
        $loopdata = $this->fillList($list, $loop, $rec->MethodOfPaymentID);
        $leftcontent->fillloop('loop_methodsofpayment', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customersources"));
        $list = $this->model->getCustomerSources($rec->CustomerTypeID);
        $loopdata = $this->fillList($list, $loop, $rec->SourceID);
        $leftcontent->fillloop('loop_customersources', $loopdata);
        
        /*
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_mortgages"));
        $list = $this->model->getList('mortgages');
        $loopdata = $this->fillList($list, $loop, $rec->MortgageID);
        $leftcontent->fillloop('loop_mortgages', $loopdata);
        */
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customersubtypes"));
        $list = $this->model->getCustomerSubtypes($rec->CustomerTypeID);
        $loopdata = $this->fillList($list, $loop, $rec->CustomerSubtypeID);
        $leftcontent->fillloop('loop_customersubtypes', $loopdata);
        
        
        
        
//        ajax_echo_r ($rec);
        
        $marketname = ($rec->MarketID==1)?"apartments":"newbuildings";
//        $innercontent = new Template($this->viewroot, "left_objects_".$marketname.".htt");
        
        $leftcontent = $this->fillLists($leftcontent, $marketname, $rec);
//        echo htmlentities ($leftcontent->output());
        
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_districts"));
//        $list = getFromCache('regions');
//        $list = json_decode($list);
        $list = $this->model->getList('districts');
        $loopdata = $this->fillList($list, $loop, $rec->DistrictID);
        $leftcontent->fillloop('loop_districts', $loopdata);
        
        if ($rec->ID) {
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_statuses"));
         $list = $this->model->getStatuses($rec->ID, 'objects');
         foreach ($list as $item) {
          $item->typepicture = $typepicture[$item->TypeID];
         }
         $loopdata = $this->fillList($list, $loop);
        } else {
         $loopdata = "";
        }
        $leftcontent->fillloop('loop_statuses', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_statustypes"));
        $list = $this->model->getList('statustypes');
//        ajax_echo_r ($list);
        $loopdata = $this->fillList($list, $loop);
        $leftcontent->fillloop('loop_statustypes', $loopdata);
        
        $leftcontent->removeloop('block_statuses');
        $leftcontent->removeloop('loop_addr_default');
        $leftcontent->fillloop('loop_addr', '');
        
        $leftcontent->fill(  '%imgfolder%' , $this->imgfolder                    );
        $leftcontent->fill(   '%viewroot%' , $this->viewroot                     );
        
        $vars = $leftcontent->getVariables();
        
        foreach ($vars as $var) {
         if ($var) {
          $leftcontent->fill(       "%".$var."%" , brtonl($rec->$var));
         }
        }
        
        $leftcontent->fillloop('loop_table', '');
        
        $leftcontent->processfcb('');
        $leftcontent->fill(  '%imgfolder%' , $this->imgfolder                    );
        $leftcontent->fill(   '%viewroot%' , $this->viewroot                     );
        
        ajax_echo (localize($leftcontent->output()));
       break;
       
       case ('addObject'):
        $ret = new stdClass;
//        if ($json->Status=='') {
//         $ret->message = $this->getMessage('addcall_error_novalue');
//         $ret->result = 0;
//        } else
        
        if (($json->UserID!=$userid) && ($userdetails->GroupID!=1)) {
         $ret->message = $this->getMessage('error_nouserrights');
         $ret->result = 0;
         $ret->canproceed = 0;
        } elseif ((date_create($json->DateTarget)) && ((int)date_format(date_create($json->DateTarget), 'Y')<1985)) {
         $ret->message = $this->getMessage('addmoney_error_nodate');
         $ret->result = 0;
         $ret->canproceed = 0;
        } elseif ($json->Phone=='') {
         $ret->message = $this->getMessage('addcall_error_nophone');
         $ret->result = 0;
         $ret->canproceed = 0;
        } else {
         $r  = $this->model->addObject($json);
//         ajax_echo_r ($r);
//         $rs = $this->model->addSubObject($json);
//         if ($r->rowsAffected>0) {
          $ret->message = $this->getMessage('addmoney_success');
          $ret->result = 1;
          $ret->canproceed = 1;
          if ($r->lastInsertID) {
           $ret->ID = $r->lastInsertID;
          } else {
           $ret->ID = $json->ID;
          }
         /*
         } else {
          $ret->message = $this->getMessage('addmoney_error_unknown');
          $ret->result = 0;
          $ret->canproceed = 1;
          $ret->ID = $json->ID;
         }
         */
        }
        echo json_encode($ret);
        
       break;
       
       case ('deleteObject'):
        $this->model->deleteObject($json);
       break;
       
       
       case ('addSubObject'):
//        ajax_echo_r ($json);
        $ret = new stdClass;
        
        if ($json->MarketID==1) {
         if ($json->Floor=='') {
          $ret->message = $this->getMessage('error_nofloor');
          $ret->result = 0;
          $ret->canproceed = 0;
          /*
         } elseif ($json->Floors=='') {
          $ret->message = $this->getMessage('error_nofloors');
          $ret->result = 0;
          $ret->canproceed = 0;
         } elseif ($json->RoomsTotal=='') {
          $ret->message = $this->getMessage('error_norooms');
          $ret->result = 0;
          $ret->canproceed = 0;
          */
         } else {
          $ret->canproceed = 1;
         }
        } else {
         if ($json->Floors=='') {
          $ret->message = $this->getMessage('error_nofloors');
          $ret->result = 0;
          $ret->canproceed = 0;
         } else {
          $ret->canproceed = 1;
         }
        }
        
        if ($ret->canproceed) {
         $r  = $this->model->addSubObject($json);
         //if ($r->rowsAffected>0) {
          $ret->message = $this->getMessage('addmoney_success');
          $ret->result = 1;
          $ret->canproceed = 1;
          if ($r->lastInsertID) {
           $ret->ID = $r->lastInsertID;
          } else {
           $ret->ID = $json->ID;
          }
         /*
         } else {
          $ret->message = $this->getMessage('addmoney_error_unknown');
          $ret->result = 0;
          $ret->canproceed = 1;
          $ret->ID = $json->ID;
         }
         */
        }
        
        echo json_encode($ret);
        
       break;
       
       
       
       case ('loadObjectDetails'):
//        ajax_echo_r ($json);
        $rec = $this->model->getObject($json, $userdetails);
//        ajax_echo_r ($rec);
        
        $marketname = ($rec->MarketID==1)?"apartments":"newbuildings";
        $innercontent = new Template($this->viewroot, "left_objects_".$marketname.".htt");
        
//        $rec_od = $this->model->getObjectDetails($rec->ID, $rec->MarketID);
        
        
        $loop = new Template($this->viewroot, $innercontent->returnloop("loop_quarters"));
        $list = $this->model->getList('quarters');
        $loopdata = $this->fillList($list, $loop, $rec_od->CompletionDateQuarter);
        $innercontent->fillloop('loop_quarters', $loopdata);
        
        $startyear = (int)date('Y') -10;
        $endyear   = (int)date('Y') +10;
        $loop = new Template($this->viewroot, $innercontent->returnloop("loop_years"));
        $list = array();
        for ($y = $startyear; $y<$endyear; $y++) {
         $list[$y] = new stdClass;
         $list[$y]->ID = $y;
         $list[$y]->Description = $y;
        }
        $loopdata = $this->fillList($list, $loop, $rec_od->CompletionDateYear);
        $innercontent->fillloop('loop_years', $loopdata);
        
        
        $leftcontent = $this->fillLists($leftcontent, $marketname, $rec);
        
        $leftcontent->fillloop('loop_table', '');
        
//        ajax_echo_r ($rec_od);
        echo $this->fillObjectDetails($innercontent, $rec_od);
       break;
       
       case ("fillnewbuildingssubitems"):
//        echo_r('deleteID: '.$json->deleteID);
//        ajax_echo_r ($json);
        
        if ($json->deleteID) {
         $this->model->deleteRow('newbuildings_subitems',$json->deleteID);
        }
        if ($json->addID) {
         $this->model->addNewbuilding($json->ID);
        }
        
        $table    = $this->model->getListByParentID('newbuildings_subitems','ParentID',$json->ID);
        
//        ajax_echo_r($table);
        
        $tmp = new Template($this->viewroot, 'left_objects_newbuildings.htt');                                          // load common parent template
        $tmp->loadloop("loop_table");
        
        if (sizeof($table)) {
         $loop_table_row = new Template($this->viewroot, $tmp->returnloop("loop_table_row"));                                          // load common parent template
         
         $block_no       = new Template($this->viewroot, $tmp->returnloop("block_no"));
         $block_no=$block_no->output();
         
         $loopdata = "";
         $c = 0;
         
         $thistable = "";
         
         $vars = $tmp->getVariables();
         
         foreach ($table as $item) {
          $loop_table_row->reload();
          
          $loop_table_row->fill("%c%",  $c%2);
          foreach ($vars as $var) {
           if ($var) {
            $loop_table_row->fill(       "%".$var."%" , $item->$var);
           }
          }
          
          $thistable.=$loop_table_row->output();
          $c++;
         }
         $tmp->fillloop('loop_table_row',$thistable);
         $tmp->fillloop(    "block_no","");
         $tmp->fillloop("block_norows","");
         
         $tmp->processfcb('');
         $tmp->fill('%imgfolder%', $this->imgfolder);
         ajax_echo (localize($tmp->output()));
        } else {
         $block_norows   = new Template($this->viewroot, $tmp->returnloop("block_norows"));
         $block_norows=$block_norows->output();
         
         ajax_echo (localize($block_norows));
        }
       break;
       
       case ("savefield"):
        $this->model->saveField($json);
       break;
       
       
       
       case ('newCustomer'):
        $leftcontent = new Template($this->viewroot, "editor_customers.htt");
        
        if ($json->ID) {
         $rec = $this->model->getCustomer($json->ID);
        } else {
         $rec = new stdClass;
         $rec->TypeID         = 1;
         $rec->Firstname      = $userdetails->Firstname;
         $rec->Surname        = $userdetails->Surname;
         $rec->UserID         = $userdetails->ID;
         $rec->HouseTypeID    = 1;
        }
        $rec->CustomerTypeID = 1;
        
//        ajax_echo_r ($rec);
        
        /*
        if ($rec->UserID!=$userid) {
         $leftcontent->fillloop('block_phone_editor','');
         $leftcontent->removeloop('block_phone_hidden');
        } else {
         $leftcontent->removeloop('block_phone_editor');
         $leftcontent->fillloop('block_phone_hidden','');
        }
        */
        
//        ajax_echo_r ($rec);
        
        
        $typepicture = array();
        $typepicture[1] = $leftcontent->returnloop('block_typepicture_1');
        $typepicture[2] = $leftcontent->returnloop('block_typepicture_2');
        $typepicture[3] = $leftcontent->returnloop('block_typepicture_3');
        $typepicture[4] = $leftcontent->returnloop('block_typepicture_4');
        $typepicture[5] = $leftcontent->returnloop('block_typepicture_5');
        $typepicture[6] = $leftcontent->returnloop('block_typepicture_6');
        
        $leftcontent->fillloop('block_typepicture_1', '');
        $leftcontent->fillloop('block_typepicture_2', '');
        $leftcontent->fillloop('block_typepicture_3', '');
        $leftcontent->fillloop('block_typepicture_4', '');
        $leftcontent->fillloop('block_typepicture_5', '');
        $leftcontent->fillloop('block_typepicture_6', '');
        
        
        if ($userdetails->GroupID!=1) {
//         $leftcontent->fillloop  ('block_phone_editor','');
//         $leftcontent->removeloop('block_phone_hidden');
         
         $leftcontent->fillloop  ('block_userselector','');
         $leftcontent->removeloop('block_nouserselector');
        } else {
//         $leftcontent->removeloop('block_phone_editor');
//         $leftcontent->fillloop  ('block_phone_hidden','');
         
         $leftcontent->removeloop('block_userselector');
         $leftcontent->fillloop  ('block_nouserselector','');
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_users"));
         $list = $this->model->getList('users');
         $loopdata = $this->fillList($list, $loop, $rec->UserID);
         $leftcontent->fillloop('loop_users', $loopdata);
        }
        
        
        $loopdata = "";
        $loop = new Template($this->viewroot, $leftcontent->returnloop("block_district"));
        $list = $this->model->getList('districts');
        $DistrictIDs = explode(";",$rec->DistrictIDs);
//        ajax_echo_r ($DistrictIDs);
        foreach ($DistrictIDs as $DistrictID) {
         if ($DistrictID) {
          $thisDistrictID = substr($DistrictID, strpos($DistrictID, "_")+1);
//          echo $thisDistrictID."<br>";
          
          $loop->reload();
          $loop->fill('%ID%', $thisDistrictID);
          $loop->fill('%Description%', $list[$thisDistrictID]->Description);
          $loopdata.= $loop->output();
         }
        }
        //$loopdata = $this->fillList($list, $loop, $rec->DistrictIDs);
//        $leftcontent->loadloop('block_popupdefault','');
        $leftcontent->fill(   '%Districts%', $loopdata);
        
        
        
        
        
        
        $loopdata = "";
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_housetypes"));
        $list = $this->model->getListEx('housetypes', '`Exclude1`=0');
        foreach ($list as $item) {
         $loop->reload();
         $loop->fill(         '%ID%', $item->ID);
         $loop->fill('%Description%', $item->Description);
         $loop->fill('%fcb_checked%', (strpos($rec->HouseTypeIDs, "_".$item->ID.";"))?"1":"");
         
         $loopdata.= $loop->output();
        }
        $leftcontent->fillloop('loop_housetypes', $loopdata);
        
        $loopdata = "";
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_desiredrooms"));
        $list = $this->model->getList('desiredrooms');
        foreach ($list as $item) {
         $loop->reload();
         $loop->fill(         '%ID%', $item->ID);
         $loop->fill('%Description%', $item->Description);
         $loop->fill('%fcb_checked%', (strpos($rec->DesiredRoomsIDs, "_".$item->ID.";"))?"1":"");
         
         $loopdata.= $loop->output();
        }
        $leftcontent->fillloop('loop_desiredrooms', $loopdata);
        
        
        
        
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_objects"));
        $list = $this->model->getObjects(false, 1);
        $loopdata = $this->fillList($list, $loop, $rec->ObjectID);
        $leftcontent->fillloop('loop_objects', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_methodsofpayment"));
        $list = $this->model->getMethodsOfPayment(1);
        $loopdata = $this->fillList($list, $loop, $rec->MethodOfPaymentID);
        $leftcontent->fillloop('loop_methodsofpayment', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customersources"));
        $list = $this->model->getCustomerSources($rec->CustomerTypeID);
        $loopdata = $this->fillList($list, $loop, $rec->SourceID);
        $leftcontent->fillloop('loop_customersources', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_calldirections"));
        $list = $this->model->getList('calldirections');
        $loopdata = $this->fillList($list, $loop, $rec->DirectionID);
        $leftcontent->fillloop('loop_calldirections', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_customersubtypes"));
        $list = $this->model->getCustomerSubtypes($rec->CustomerTypeID);
        $loopdata = $this->fillList($list, $loop, $rec->CustomerSubtypeID);
        $leftcontent->fillloop('loop_customersubtypes', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_markets"));
        $list = $this->model->getList('markets');
        $loopdata = $this->fillList($list, $loop, $rec->MarketID);
        $leftcontent->fillloop('loop_markets', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_floors"));
        $list = $this->model->getListOrdered('floors','Order');
        $loopdata = $this->fillList($list, $loop, $rec->CustomerSubtypeID);
        $leftcontent->fillloop('loop_floors', $loopdata);
        
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_districts"));
//        $list = getFromCache('regions');
//        $list = json_decode($list);
        $list = $this->model->getList('districts');
        $loopdata = $this->fillList($list, $loop, $rec->DistrictID);
        $leftcontent->fillloop('loop_districts', $loopdata);
        
        if ($rec->ID) {
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_statuses"));
         $list = $this->model->getStatuses($rec->ID, 'customers');
         foreach ($list as $item) {
          $item->typepicture = $typepicture[$item->TypeID];
         }
         $loopdata = $this->fillList($list, $loop);
        } else {
         $loopdata = "";
        }
        $leftcontent->fillloop('loop_statuses', $loopdata);
        
        $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_statustypes"));
        $list = $this->model->getList('statustypes');
        $loopdata = $this->fillList($list, $loop);
        $leftcontent->fillloop('loop_statustypes', $loopdata);
        
        $leftcontent->removeloop('block_statuses');
        $leftcontent->removeloop('loop_addr_default');
        $leftcontent->fillloop('loop_addr', '');
        
        $leftcontent->fill(  '%imgfolder%' , $this->imgfolder                    );
        $leftcontent->fill(   '%viewroot%' , $this->viewroot                     );
        
        $block_district = new Template($this->viewroot, $leftcontent->returnloop("block_district"));
        
        $vars = $leftcontent->getVariables();
//        ajax_echo_r ($vars);
        foreach ($vars as $var) {
         if ($var) {
          $leftcontent->fill(       "%".$var."%" , brtonl($rec->$var));
         }
        }
        
        $leftcontent->fillloop("block_district", $block_district->output());
        
        $leftcontent->processfcb('');
        $leftcontent->fill(  '%imgfolder%' , $this->imgfolder                    );
        $leftcontent->fill(   '%viewroot%' , $this->viewroot                     );
        
//        $leftcontent->processfcb('');
//        ajax_echo (htmlentities(localize($leftcontent->output())));
        
        ajax_echo (localize($leftcontent->output()));
       break;
       
       case ('addCustomer'):
//        ajax_echo_r ($json);
        
        $ret = new stdClass;
        if (($json->UserID!=$userid) && ($userdetails->GroupID!=1)) {
         $ret->message = $this->getMessage('error_nouserrights');
         $ret->result = 0;
         $ret->canproceed = 0;
        } elseif ($json->Phone=='') {
         $ret->message = $this->getMessage('addcall_error_nophone');
         $ret->result = 0;
         $ret->canproceed = 0;
        } elseif ((date_create($json->DateTarget)) && ((int)date_format(date_create($json->DateTarget), 'Y')<1985)) {
         $ret->message = $this->getMessage('addmoney_error_nodate');
         $ret->result = 0;
         $ret->canproceed = 0;
        } elseif ($json->Status=='') {
         $ret->message = $this->getMessage('addcall_error_novalue');
         $ret->result = 0;
         $ret->canproceed = 0;
        } else {
//         if ($this->model->addCustomer($json)) {
         $r = $this->model->addCustomer($json);
          $ret->message = $this->getMessage('addmoney_success');
          $ret->result = 1;
          $ret->canproceed = 1;
          if ($r->lastInsertID) {
           $ret->ID = $r->lastInsertID;
          } else {
           $ret->ID = $json->ID;
          }
         /*
         } else {
          $ret->message = $this->getMessage('addmoney_error_unknown');
          $ret->result = 0;
          $ret->canproceed = 1;
         }
         */
        }
        
//        ajax_echo_r ($ret);
        
        
        echo json_encode($ret);
        
       break;
       
       case ('deleteCustomer'):
        $this->model->deleteCustomer($json);
       break;       
       
       
       
       
       
       
       
       
       case ('addStatus'):
//        ajax_echo_r ($json);
        
        if ($json->ParentID) {
         if ($json->Address && $json->Comment) {
          $ret = $this->model->addStatus($json);
//          ajax_echo_r ($ret);
         }
         
         $leftcontent = new Template($this->viewroot, "editor_objects.htt");
         $typepicture = array();
         $typepicture[1] = $leftcontent->returnloop('block_typepicture_1');
         $typepicture[2] = $leftcontent->returnloop('block_typepicture_2');
         $typepicture[3] = $leftcontent->returnloop('block_typepicture_3');
         $typepicture[4] = $leftcontent->returnloop('block_typepicture_4');
         $typepicture[5] = $leftcontent->returnloop('block_typepicture_5');
         $typepicture[6] = $leftcontent->returnloop('block_typepicture_6');
         
         $leftcontent->loadloop('block_statuses','');
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_statuses"));
         $list = $this->model->getStatuses($json->ParentID, $json->ParentName);
         foreach ($list as $item) {
          $item->typepicture = $typepicture[$item->TypeID];
         }
         $loopdata = $this->fillList($list, $loop);
         $leftcontent->fillloop('loop_statuses', $loopdata);
         
         $leftcontent->fillloop('block_typepicture_1', '');
         $leftcontent->fillloop('block_typepicture_2', '');
         $leftcontent->fillloop('block_typepicture_3', '');
         $leftcontent->fillloop('block_typepicture_4', '');
         $leftcontent->fillloop('block_typepicture_5', '');
         $leftcontent->fillloop('block_typepicture_6', '');
         
         $leftcontent->fill(  '%imgfolder%' , $this->imgfolder                    );
         $leftcontent->fill(   '%viewroot%' , $this->viewroot                     );
         $leftcontent->processfcb('');
         ajax_echo (localize($leftcontent->output()));
        }
       break;
       
       case ('fillSelect'):
        $tmp = new Template($this->viewroot, 'left_objects.htt');
        $t   = new Template($this->viewroot, $tmp->returnloop("loop_addr"));
        
        $req = "http://rosreestr.ru/api/online/regions/".$json->elsid;
        $f = file_get_contents($req);
        $j = json_decode($f);
        
        sortbyname ($j);
        
        $loopitems = $this->fillSelect($t, $j);
//        $t = new Template($this->viewroot, $tmp->returnloop("loop_addr_default"));
//        $loop_addr_default = $t->output();
        
        echo localize($loopitems);
       break;
       
       case ('ownlogin'):
        $key = getvariablereq('key');          // get JSON data from the request
        $login = $this->model->ownLogin($key);
        
        if ($login) {
         $tmp = new Template($this->viewroot, 'redir.htt');
         $tmp->fill('%Link%', '?go=users');
         
         setsecurevariable('userid',$login->ID);
         setsecurevariable('tmpcnt',0         );
         
        } else {
         $tmp = new Template($this->viewroot, 'wrong_key.htt');
        }
        
        echo localize($tmp->output());
       break;
       
       case ('sendEmail'):
        $user = $this->model->getUser($json->ID);
        
        $tmp = new Template($this->viewroot, 'mail1.htt');
        $tmp->fill(  "%LoginKey%", $user->LoginKey  );
        $tmp->fill( "%Firstname%", $user->Firstname );
        
        $mail = localize($tmp->output());
        
        $is_sent = sendmail("Welcome to Izum", $mail, $user->Email);
        
        echo "Emails sent: ".$is_sent."<br>";
        
       break;
       
       case ('setUserStatus'):
        if ($json->ID) {
         $sta = $this->model->setUserStatus($userdetails->ID, $json->ID);
        }
        $sta = $this->model->getUserStatus($userdetails->ID);
        
        $tmp = new Template($this->viewroot, 'mainparent.htt');                                       // load common parent template
        
        $tmp->loadloop('block_status_'.$sta,'');
        
        $tmp->processfcb('');
        $tmp->fill(  '%imgfolder%' , $this->imgfolder                    );
        $tmp->fill(   '%viewroot%' , $this->viewroot                     );
        ajax_echo (localize($tmp->output()));
       break;
       
       
       
       
       case ('getBalance'):
        $bal = $this->model->getBalance();
        
        $tmp = new Template($this->viewroot, 'left_money.htt');                                       // load common parent template
        
        $tmp->loadloop('block_balance');
        $tmp->fill('%balance%', $bal);
        
        $tmp->processfcb('');
        $tmp->fill(  '%imgfolder%' , $this->imgfolder                    );
        $tmp->fill(   '%viewroot%' , $this->viewroot                     );
        ajax_echo (localize($tmp->output()));
       break;
       
       case ('exportToExcel'):
        echo $this->model->backup();
       break;
       
       default:                                                                   // for me if I miss something.
        ajax_echo ("Unknown AJAX action: ".$action);
       break;
       
      }
     } else {
      echo "You need to login again.";
      
     }
    } else {                                                                        // for plain HTML (not an Ajax)
     if (((int)$userdetails->AccountType==1) && (isset($_GET['admin']))) {         // if the admin wants to see his admin-panel
      $tmp = new Template($this->viewroot, 'adminparent.htt');                                      // load common parent template
      $tmp_clean = clone $tmp;
      $tmp->loadloop('selectcompanymsg');
      $selectcompanymsg = $tmp->output();
      
      $tmp = clone $tmp_clean;                                                     // load common parent template
      $tmp->loadloop("adminpage".$_GET['admin']);
      
      if ($_GET['admin']==3) {
       $tmp->fillloop('loop_table', "");
      }
      
      $thispage = $tmp->output();
      $tmp = clone $tmp_clean;                                                     // load common parent template
      $tmp->fillloop("adminpages_parent"  ,  $thispage);
      
      $tmp->fillloop(  "companydetails","" );
      $tmp->fillloop(    "agentdetails","" );
      $tmp->fillloop("selectcompanymsg","" );
      
      $tmp->fill(    "%list_agents%", $selectcompanymsg                 );
      $tmp->fill(     "%justlogged%", $userdetails->JustLogged          );         // fill template
      $tmp->fill(  "%numoldrecords%", -2                                );         // fill the template with actual data
//         $tmp->fill("%imgfolder%"      , "../".$this->settings->imgfolder        );        // fill the template with actual data
      $tmp->fill(      "%tableview%", $this->imgfolder                  );         // fill the template with actual data
      $tmp->fill(      "%agentname%", $userdetails->FirstName." ".$userdetails->LastName                );        // fill the template with actual data
      $tmp->fill(    "%projectname%", $this->settings->projectname    );        // fill the template with actual data
      
      for ($n=0; $n<10; $n++) {
       $tmp->fill("%".$n."%"        , ($n==(int)$_GET['admin']?"active":""));       // fill the template with actual data
      }
      
      $this->processstylesheet($hue,'admin');
      
      echo localize($tmp->output());                                               // show it
      
     } else {                                                                      // if the user or manager wants to see his/her GUI
      $id      = getvariablereq('id'     );
      $auxmode = getvariablereq('auxmode');
      
      $go      = getvariablereq('go');
      if (!$go) $go="objects";
      
      $tmp = new Template($this->viewroot, 'mainparent.htt');                                       // load common parent template
      
      $tmp->fillloop('block_status_1','');
      $tmp->fillloop('block_status_2','');
      
      $privileges_this = $this->model->getUserPrivileges($userid);
      $privileges_all  = $this->model->getUserPrivileges(0);
      
      if (!$privileges_this[$go]) $go=array_keys($privileges_this)[0];
      $allowed = 0;
      
      foreach ($privileges_this as $p) {
       $tmp->removeloop('block_link_'.$p->PageName);
       if ($go == $p->PageName) $allowed = 1;
      }
      
      foreach ($privileges_all as $p) {
       $tmp->fillloop('block_link_'.$p->PageName, '');
      }
      
      if (!$allowed) {
       $go = $privileges_this[0]->PageName;
      }
      
      
      $loop_clean = clone $tmp;
      
      if ($userid>-1) {
       $tmp_user_info = clone $loop_clean;
       $tmp_user_info->loadloop("loop_user_info");
       
       $tmp_user_info->fill("%username%",$userdetails->Username);
       
       $loop_user_auth=$tmp_user_info->output();
       
       $tmp->removeloop("loop_globalparent");
       $tmp->fillloop("loop_user_auth"      , $loop_user_auth );
       $tmp->fillloop("loop_user_info"      , ""              );
       
       
       
       
       $leftcontent = new Template($this->viewroot, 'left_'  .$go.'.htt');                                    // load common parent template
       $maincontent = new Template($this->viewroot, 'main_'  .$go.'.htt');                                    // load common parent template
       
       
       switch ($go) {
        case ('objects'):
         $messages = array('savefirst_nbs');
        break;
       }
       
       if ($messages) {
        if (sizeof($messages)) {
         $tmp_messages = new Template($this->viewroot, $tmp->returnloop('loop_messages'));
         foreach ($messages as $message) {
          $tmp_messages->reload();
          $msg = $this->getMessage($message);
          $tmp_messages->fill("%name%", $message);
          $tmp_messages->fill("%text%", $msg);
          $loop_messages.= trim($tmp_messages->output());
         }
        }
       }
       
       
       $tmp->fillloop('loop_messages'       ,$loop_messages);
       
       
       if ($userdetails->GroupID==1) {
        $leftcontent->removeloop('block_adminonly');
       } else {
        $leftcontent->fillloop('block_adminonly', '');
       }
       
       $leftcontent->fill('%today%', date("Y-m-d"));
       
       switch ($go) {
        case ('dashboard'):
         $maincontent->fillloop('block_dashboard', '');
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_users"));
         $list = $this->model->getUsersLst();
         $loopdata = $this->fillList($list, $loop, $rec->UserID);
         $leftcontent->fillloop('loop_users', $loopdata);
         
        break;
        case ('users'):
         
         
         
        break;
        case ('money'):
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_typeids"));
         $list = $this->model->getList('moneyrecordtypes');
         $loopdata = $this->fillList($list, $loop, 0);
         $leftcontent->fillloop('loop_typeids', $loopdata);
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_groupids"));
         $list = $this->model->getExpenditureGroups(0);
         $loopdata = $this->fillList($list, $loop, 0);
         $leftcontent->fillloop('loop_groupids', $loopdata);
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_months"));
         $list = $this->model->getMonths('money','DateAdded');
         $loopdata = $this->fillList($list, $loop, 0);
         $leftcontent->fillloop('loop_months', $loopdata);
         
         $leftcontent->fillloop( 'block_popupdefault','' );
         $leftcontent->fillloop(      'block_balance','' );
         
         
         
         
         
         
         
         
        break;
        case ('tasks'):
         $block_item = new Template($this->viewroot, $leftcontent->returnloop('block_AssignedTo'));
         $users=$this->model->getUsers();
         
         $thislist = "";
         if (sizeof($users)) {
          foreach ($users as $item) {
           $block_item->reload();
           $block_item->fill(        "%ID%", $item->ID           );
           $block_item->fill(  "%Username%", $item->Username     );
           $block_item->fill(  "%selected%", ($item->ID==$userid)?"selected":""     );
           $thislist.=$block_item->output();
          }
         }
         
         $leftcontent->fillloop ('block_AssignedTo', $thislist);
         
         
         
         
         $ProjectID = getvariable('ProjectID');
         setsecurevariable('ProjectID', $ProjectID);
         
         $TaskListID = getvariable('TaskListID');
         setsecurevariable('TaskListID', $TaskListID);
         
         $block_item = new Template($this->viewroot, $leftcontent->returnloop('block_ProjectID'));
         $users=$this->model->getObjects();
         
         $thislist = "";
         if (sizeof($users)) {
          foreach ($users as $item) {
           $block_item->reload();
           $block_item->fill(       "%ID%" , $item->ID           );
           $block_item->fill(    "%Title%" , $item->Title        );
           $block_item->fill(  "%selected%", ($item->ID==$ProjectID)?"selected":""     );
           $thislist.=$block_item->output();
          }
         }
         
         $leftcontent->fillloop ('block_ProjectID', $thislist);
         
         
         
         $block_item = new Template($this->viewroot, $leftcontent->returnloop('block_TaskListID'));
         $users=$this->model->getList('tasklists');
         
         
         $thislist = "";
         if (sizeof($users)) {
          foreach ($users as $item) {
           $block_item->reload();
           $block_item->fill(       "%ID%" , $item->ID           );
           $block_item->fill(    "%Title%" , $item->Title        );
           $block_item->fill(  "%selected%", ($item->ID==$TaskListID)?"selected":""     );
           $thislist.=$block_item->output();
          }
         }
         
         $leftcontent->fillloop ('block_TaskListID', $thislist);
         
         
         
         $block_item = new Template($this->viewroot, $leftcontent->returnloop('block_PriorityID'));
         $users=$this->model->getList('priorities');
         
         $thislist = "";
         if (sizeof($users)) {
          $c=0;
          foreach ($users as $item) {
           $block_item->reload();
           $block_item->fill(        "%ID%" , $item->ID           );
           $block_item->fill(     "%Title%" , $item->Title        );
           $block_item->fill(         "%c%" , $c==2               );
           $thislist.=$block_item->output();
           $c++;
          }
         }
         $leftcontent->fillloop ('block_PriorityID', $thislist);
         
         
         
         
         
         $loop     = clone $maincontent;
         $loop->loadloop("loop_objects");
         
         $list = $this->model->getObjects();
         
         $loopdata = "";
         if (sizeof($list)>0)  {
          foreach ($list as $ik=>$iv) {
           $loop->reload();
           $loop->fill(   "%Description%" ,  $iv->Description );
           $loop->fill(            "%id%" ,  $iv->ID          );
           $loop->fill(      "%selected%" ,  ""               );
           $loopdata.=$loop->output();
          }
         }
         
         $maincontent->fillloop('loop_objects', $loopdata);
         
         
         
         
         
         
        break;
        case ('customers'):
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_markets"));
         $list = $this->model->getList('markets');
         $loopdata = $this->fillList($list, $loop, 0);
         $leftcontent->fillloop('loop_markets', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_desiredrooms"));
         $list = $this->model->getList('desiredrooms');
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill('%fcb_checked%', (strpos($rec->DesiredRoomsIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop('loop_desiredrooms', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_districts"));
         $list = $this->model->getListEx('districts', "`ID`>1");
//         ajax_echo_r ($list);
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill('%fcb_checked%', (strpos($rec->HouseTypeIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop(   'loop_districts', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_districts_2"));
         $list = $this->model->getListEx('districts', "`ParentID`=2");
//         ajax_echo_r ($list);
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill(       '%Desc%', $item->Desc       );
          $loop->fill('%fcb_checked%', (strpos($rec->HouseTypeIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop(   'loop_districts_2', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_districts_1"));
         $list = $this->model->getListEx('districts', "`ParentID`=1");
//         ajax_echo_r ($list);
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill('%fcb_checked%', (strpos($rec->HouseTypeIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop(   'loop_districts_1', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_housetypes"));
         $list = $this->model->getListEx('housetypes', '`Exclude1`=0');
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill('%fcb_checked%', (strpos($rec->HouseTypeIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop('loop_housetypes', $loopdata);
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_users"));
         $list = $this->model->getUsersFlt('customers');
         $loopdata = $this->fillList($list, $loop, $userid);
         $leftcontent->fillloop('loop_users', $loopdata);
         
        case ('objects'): 
         $maincontent->fillloop(   "block_dir0","" );
         $maincontent->fillloop(   "block_dir1","" );
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_markets"));
         $list = $this->model->getList('markets');
         $loopdata = $this->fillList($list, $loop, 0);
         $leftcontent->fillloop('loop_markets', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_desiredrooms"));
         $list = $this->model->getList('desiredrooms');
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill('%fcb_checked%', (strpos($rec->DesiredRoomsIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop('loop_desiredrooms', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_districts_2"));
         $list = $this->model->getListEx('districts', "`ParentID`=2");
//         ajax_echo_r ($list);
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill(       '%Desc%', $item->Desc       );
          $loop->fill('%fcb_checked%', (strpos($rec->HouseTypeIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop(   'loop_districts_2', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_districts_1"));
         $list = $this->model->getListEx('districts', "`ParentID`=1");
//         ajax_echo_r ($list);
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill('%fcb_checked%', (strpos($rec->HouseTypeIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop(   'loop_districts_1', $loopdata);
         
         $loopdata = "";
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_housetypes"));
         $list = $this->model->getListEx('housetypes', '`Exclude1`=0');
         foreach ($list as $item) {
          $loop->reload();
          $loop->fill(         '%ID%', $item->ID);
          $loop->fill('%Description%', $item->Description);
          $loop->fill('%fcb_checked%', (strpos($rec->HouseTypeIDs, "_".$item->ID.";"))?"1":"");
          $loopdata.= $loop->output();
         }
         $leftcontent->fillloop('loop_housetypes', $loopdata);
         
         $loop = new Template($this->viewroot, $leftcontent->returnloop("loop_users"));
         $list = $this->model->getUsersFlt('objects');
         $loopdata = $this->fillList($list, $loop, $userdetails->ID);
         $leftcontent->fillloop('loop_users', $loopdata);
         
        break;
       }
       
       $tmp->fill("%leftcontent%"      , $leftcontent->output());                                      // fill the template with actual data
       $tmp->fill("%maincontent%"      , $maincontent->output());                                      // fill the template with actual data
       
       $tmp->fillloop("loop_menu_toplevel_items"      , $menu_toplevel_items);
       $tmp->fill("%menu_items%"               , $menu_items         );
       $tmp->fill("%fromajax%"                 , 0                   );
       
       $json = getFromCache('settings');
       
       $tmp->fill('%settings_pl_startrow_defvalue%' ,   $json->settings_pl_startrow );
       $tmp->fill('%settings_pl_startcol_defvalue%' ,   $json->settings_pl_startcol );
       $tmp->fill('%settings_pl_namescol_defvalue%' ,   $json->settings_pl_namescol );
       $tmp->fill('%settings_pl_idscol_defvalue%'   ,   $json->settings_pl_idscol   );
       $tmp->fill('%settings_pl_costscol_defvalue%' ,   $json->settings_pl_costscol );
       
       $tmp->fill('%settings_o_startrow_defvalue%'  ,   $json->settings_o_startrow  );
       $tmp->fill('%settings_o_idscol_defvalue%'    ,   $json->settings_o_idscol    );
       $tmp->fill('%settings_o_qtyscol_defvalue%'   ,   $json->settings_o_qtyscol   );
       $tmp->fill('%settings_o_costscol_defvalue%'  ,   $json->settings_o_costscol  );
       $tmp->fill('%settings_o_sumscol_defvalue%'   ,   $json->settings_o_sumscol   );
       
       $tmp->fillloop('block_split_none'       ,'');
       $tmp->fillloop('block_split_vertical'   ,'');
       $tmp->fillloop('block_split_horizontal' ,'');
       
       switch ($go){
        case ('tasks'):
         $tmp->fillloop('block_notasks','');
         
         $tmp->fillloop('loop_task0'       ,'');
         $tmp->fillloop('loop_task1'       ,'');
         $tmp->fillloop('loop_task2'       ,'');
         $tmp->fillloop('loop_task3'       ,'');
         $tmp->fillloop('block_comment'    ,'');
        break;
       }
       
       $tmp->fillloop('loop_table'    ,'');
       $tmp->fillloop('block_no'      ,'');
       $tmp->fillloop('block_norows'  ,'');
       $tmp->fillloop("block_limited" ,"");
       
       $params = new stdClass();
       
       $params->tablename   = $objecttype;
       $params->OfferTypeID = "2";
       
       $tmp->fill("%justlogged%"         , $userdetails->JustLogged          );        // fill template
       $tmp->fill("%tableview%"          , $this->imgfolder                  );        // fill the template with actual data
       $tmp->fill("%agentname%"          , $userdetails->FirstName." ".$userdetails->LastName                );        // fill the template with actual data
       
       
       
       
       
      } else {
       $loop_user_auth=$loop_clean->returnloop("loop_user_auth");
       $tmp->fillloop("loop_globalparent", $loop_user_auth);
      }
      
      $this->processstylesheet($hue,'style');
      $tmp->fill("%theme%"              , $userdetails->Theme                 );
      $tmp->fill('%objecttype%'         , $objecttype                         );
      $tmp->fill('%SmoothAnimation%'    , $SmoothAnimation                    );
      $tmp->fill('%auxmode%'            , $auxmode                            );
      
      if ((int)$userdetails->AccountType!=1) {
       $tmp->fillloop("adminpanellink","");
      }
      $tmp->fillloop("block_link","");
      
      
      
      
      
      
      
      
      
      
      
      
      
      
      
      
      $tmp->fillloop(     "block_no","" );
      $tmp->fillloop( "block_norows","" );
      $tmp->fillloop(   "block_dir0","" );
      $tmp->fillloop(   "block_dir1","" );
      
      
      
      
      $tmp->fill( "%projectname%", $this->settings->projectname    );        // fill the template with actual data
      $tmp->fill(         '%go%' , $go                                 );
      $tmp->fill(         '%id%' , $id                                 );
      $tmp->processfcb('');
      
      $tmp->fill(  '%imgfolder%' , $this->imgfolder                    );
      $tmp->fill(   '%viewroot%' , $this->viewroot                     );
      
      
      echo localize($tmp->output());                                              // show it
     }
    }
   }
  }
  
  function getstylesheet($hue,$viewroot,$filename) {
   addtolog("Controller getstylesheet begin");
   $style = "";
   $stylefilename = $filename.".less";
   $f = file_get_contents($this->viewroot."/styles/".$stylefilename);
   $f = str_replace(             "%hue%", $hue                           , $f);
   $f = str_replace( "%SmoothAnimation%", $SmoothAnimation               , $f);
   $f = str_replace(    "%previewwidth%", $this->settings->previewwidth  , $f);
   $f = str_replace(   "%previewheight%", $this->settings->previewheight , $f);
   $f = str_replace(       "%imgfolder%", $viewroot."/img"               , $f);
   $f = str_replace(        "%viewroot%", $viewroot                      , $f);
   
   $style.=($this->less->compile($f));
   addtolog("Controller getstylesheet end");
   return ($style);
  }
  
  function processstylesheet($hue,$filename) {
   addtolog("Controller processstylesheet begin");
   file_put_contents($this->viewroot."/styles/".$filename.".css",$this->getstylesheet($hue,$this->viewroot,$filename)        );         // fill the template with compiled less stylesheet
   addtolog("Controller processstylesheet end");
  }
  
  public function import() {                         // import functionality to get data from ISCentre
   addtolog("Controller import begin");
   include_once ("controller/DBImport.php");
   $this->dbimport = new DBImport($this->settings);  // instantiate a class
   
   $action = getvariablereq   ('action');            // get action from the request
   $data   = getvariablereq   ('data'  );            // get JSON data from the request
   
   $data = str_replace('\"','"',$data);              // fix some escaped paths (if any)
   
   $mtime = microtime(true);
   $thisfolder = substr($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'],0,strrpos($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME'] ,"/"))."/data/temp/dbupdate";
   @mkdirr ($thisfolder);
   
   switch ($action) {
    case ('update'):
     $this->dbimport->importtables($thisfolder);
     echo "Job done\n";
    break;
    default:
     if (sizeof($_FILES)>0) {
      foreach ($_FILES as $file) {
       if (file_exists($thisfolder."/".$file['name'])) {
        unlink($thisfolder."/".$file['name']);
       }
       move_uploaded_file ($file['tmp_name'],$thisfolder."/".$file['name']);
       echo "File accepted";
      }
     }
    break;
   }
   
   $fieldid = array();
   
   addtolog("Controller import end");
  }
  
  public function getComments($json) {
   $Comments = $this->model->getComments($json);
   
   $loop = new Template($this->viewroot, 'main_tasks.htt');                                      // load common parent template
   $block_comment = new Template($this->viewroot, $loop->returnloop('block_comment'));
   $block_comments = "";
   if (sizeof($Comments)) {
    foreach ($Comments as $comment) {
     $block_comment->reload();
     $block_comment->fill('%ID%'        ,$comment->ID        );
     $block_comment->fill('%Comment%'   ,$comment->Comment   );
     $block_comment->fill('%DateAdded%' ,$comment->DateAdded );
     $block_comment->fill('%Username%'  ,$comment->Username  );
     $block_comment->fill('%ParentID%'  ,$comment->ParentID  );
     
     $block_comment->processfcb('');
     $block_comment->fill(  '%imgfolder%' , $this->imgfolder                    );
     $block_comment->fill(   '%viewroot%' , $this->viewroot                     );
     $block_comments .= $block_comment->output();
    }
   }
   return localize($block_comments);
  }
  
  function fillList($list, $loop, $selectedid=0, $key="") {
   $vars = $loop->getVariables();
//   ajax_echo_r ($vars);
//   echo $selectedid."<br>";
   $loopdata = "";
   if (sizeof($list)>0)  {
    foreach ($list as $ik=>$iv) {
     $loop->reload();
     
     if ($key) {
      $loop->fill(      "%selected%",  ($iv->$key==$selectedid)?"selected":"");
      $loop->fill(   "%fcb_checked%",  ($iv->$key==$selectedid)?"1":""       );
     } else {
      $loop->fill(      "%selected%",  ($iv->ID  ==$selectedid)?"selected":"");
      $loop->fill(   "%fcb_checked%",  ($iv->ID  ==$selectedid)?"1":""       );
     }
     
     foreach ($vars as $var) {
      if ($var) {
       $loop->fill(       "%".$var."%" , $iv->$var);
      }
     }
     $loopdata.=$loop->output();
    }
   }
   
   return $loopdata;
  }
  
  function getUserPrivileges($json) {
   $list = $this->model->getUserPrivileges($json->UserID);
   
   $loop_par = new Template($this->viewroot,'left_users.htt');                                        // load common parent template
   $loop_par->loadloop('block_p');
   
   $loop_this = clone $loop_par;
   $loop_all  = clone $loop_par;
   
   $loop_this->loadloop('loop_this');
   $loop_all->loadloop('loop_all');
   
   $vars = $loop_this->getVariables();
   $loop = "";
   if (sizeof($list)) {
    foreach ($list as $data) {
     if ($data->Email) {
      $loop_this->reload();
      foreach ($vars as $var) {
       if ($var) {
        $loop_this->fill(       "%".$var."%" , $data->$var);
       }
      }
      $loop.=$loop_this->output();
     }
    }
   }
   $loop_par->fillloop('loop_this', $loop);
   
   $list_all = $this->model->getUserPrivileges();
   $vars = $loop_all->getVariables();
   $loop = "";
   if (sizeof($list_all)) {
    foreach ($list_all as $k=>$data) {
     if (!$list[$k]) {
      $loop_all->reload();
      foreach ($vars as $var) {
       if ($var) {
        $loop_all->fill(       "%".$var."%" , $data->$var);
       }
      }
      $loop.=$loop_all->output();
     }
    }
   }
   $loop_par->fillloop('loop_all', $loop);
   
   $loop_par->processfcb('');
   $loop_par->fill(  '%imgfolder%' , $this->imgfolder                    );
   $loop_par->fill(   '%viewroot%' , $this->viewroot                     );
   return localize($loop_par->output());                                               // show it
  }
  
  function fillSelect($looptmp, $requests, $selectedID="") {
   $b = "";
   foreach ($requests as $k=>$v) {
    $looptmp->reload();
    $looptmp->fill(          "%id%", $v->id   );
    $looptmp->fill(        "%name%", $v->name );
    $b.=$looptmp->output();
   }
   return $b;
  }
  
  function getMessage($id) {
   $tmp = new Template($this->viewroot,'messages.htt');
   $tmp->loadloop("block_".$id);
   $tmp->fill('%imgfolder%', getrootdir().$this->imgfolder);
   return localize($tmp->output());
  }
  
  function fillObjectDetails($loop, $rec) {
//   ajax_echo_r ($rec);
   $vars = $loop->getVariables();
//   ajax_echo_r ($vars);
   foreach ($vars as $var) {
    if ($var) {
     $loop->fill(       "%".$var."%" , $rec->$var);
    }
   }
   
   $loop->processfcb('');
   $loop->fill(  '%imgfolder%' , $this->imgfolder          );
   $loop->fill(   '%viewroot%' , $this->viewroot           );
   return localize($loop->output());
  }
  
  function fillLists($template, $marketname, $rec_od) {
//   ajax_echo_r ($template);
   /*
   switch ($marketname) {
    case ('apartments'):
    break;
    case ('newbuildings'):
    break;
    
    default:
     echo "controller->fillLists: unknown marketname ".$marketname;
    break;
   }
   */
   
   $lists = array('housetypes' , 'wallsmaterials' , 'overlappingtypes' , 'layouttypes' , 'conditions' , 'toilettypes' , 'exchangeoptions' , 'offertypes' , 'relationships' , 'selltypes' ,'finishings' , 'floorsurfaces' , 'stovetypes' , 'doorstypes' , 'wallssurfaces' , 'bathroomequipments' , 'windowstypes' , 'rightssources' , 'rightstransmissions' , 'markets' , 'calldirections', 'mediators' , 'quarters'              );
   $names = array('HouseTypeID', 'WallsMaterialID', 'OverlappingTypeID', 'LayoutTypeID', 'ConditionID', 'ToiletTypeID', 'ExchangeOptionID', 'OfferTypeID', 'RelationshipID', 'SellTypeID','FinishingID', 'FloorSurfaceID', 'StoveTypeID', 'DoorsTypeID', 'WallsSurfaceID', 'BathroomEquipmentID', 'WindowsTypeID', 'RightsSourceID', 'RightsTransmissionID', 'MarketID', 'DirectionID'   , 'MediatorID', 'CompletionDateQuarter' );
   
   if (sizeof($lists)) {
    foreach ($lists as $k=>$list) {
     $table = $this->model->getList($list);
     
     $thisname = $names[$k];
     $loopstr = "";
//     $items = $loop->returnloop(, $table);
     $loop = new Template($this->viewroot, $template->returnloop('loop_'.$list));
     foreach ($table as $item) {
      $loop->reload();
      
      $loop->fill(         '%ID%', $item->ID          );
      $loop->fill('%Description%', $item->Description );  // 9379998389 Сергей Kinup
      $loop->fill(   "%selected%", ($item->ID==$rec_od->$thisname)?"selected":""     );
      
      $loopstr.=$loop->output();
     }
     $template->fillloop('loop_'.$list, $loopstr);
    }
   }
   
   return $template;
  }
  
  function fillObjects($tmp_src, $table, $json, $userdetails, $tmp_src_subitems=0) {
//   ajax_echo_r ($json);
   
   if ($userdetails->GroupID==1) {
    $tmp_src->removeloop('block_adminonly');
   } else {
    $tmp_src->fillloop('block_adminonly', '');
   }
   
   $block_no         = new Template($this->viewroot, $tmp_src->returnloop('block_no'));
   $block_no=$block_no->output();
   
   $block_norows     = new Template($this->viewroot, $tmp_src->returnloop('block_norows'));
   $block_norows=$block_norows->output();
   
   $checkbox_th      = new Template($this->viewroot, $tmp_src->returnloop('checkbox_th'));
   $checkbox_td      = new Template($this->viewroot, $tmp_src->returnloop('checkbox_td'));
   
   $loop_table_row = new Template($this->viewroot, $tmp_src->returnloop('loop_table_row'));
   
   $block_dir = array();
   $block_dir[0]     = new Template($this->viewroot, $tmp_src->returnloop('block_dir0'));
   $block_dir[1]     = new Template($this->viewroot, $tmp_src->returnloop('block_dir1'));
   
   $block_dir[0] = $block_dir[0]->output();
   $block_dir[1] = $block_dir[1]->output();
   
   $loopdata = "";
   $loopdata.=$checkbox_th->output();
   
   $cols = array();
   $cols[] = 'ID';
   $cols[] = 'Date';
   $cols[] = 'Market';
   $cols[] = 'RoomsTotal';
   $cols[] = 'HouseType';
   $cols[] = 'District';
   $cols[] = 'Address';
   $cols[] = 'ExchangeOption';
   $cols[] = 'Firstname';
   $cols[] = 'Surname';
   $cols[] = 'Cost';
   
   $emptyinfo = file_get_contents($this->viewroot.'/templates/emptyinfo.htt');
   $c = 0;
   
   $thistable = "";
   if (sizeof($table)) {
    $thisidcolumnname = $json->tablename."_ID";
//          echo $thisidcolumnname."<br>";
    foreach ($table as $item) {
     $loop_table_row->reload();
     $thisrow   = "";
     $checkbox_td->reload();
     $checkbox_td->fill('%checked%' , 'checked'            );
     $checkbox_td->fill('%id%'      , $item->$thisidcolumnname );
     $thisrow.=$checkbox_td->output();
     
     $loop_table_row->fill(           "%ID%", $item->ID);
     $loop_table_row->fill( "%calldiretion%", $block_dir[$item->DirectionID]);
     
     foreach ($cols as $cv) {
      $k=$cv;
      $v = $item->$k;
      if ($k && ($k!='CustomerID')) {
       if ($k==$json->tablename."_AuxInfo") {
        if (trim($v)=="") $v=$emptyinfo;
       }
       
       switch ($cv) {
        case ('Cost'):
         $loop_table_row->fill(  "%".$k."%", formatCost($v));
        break;
        default:
         $loop_table_row->fill(  "%".$k."%", (((int)$v==0) && ((string)(int)$v==$v) && ($k!='Cost'))?$block_no:($v));
        break;
       }
      }
     }
     $loop_table_row->fillloop('loop_table_cell',$thisrow);
//             $loop_table_row->fill("%c%",  ($c%2) + ((date_timestamp_get(date_create($item->DateTarget))<date_timestamp_get(date_create()) )?0:2) );
     $loop_table_row->fill("%c%",  $c%2);
     
     if ($tmp_src_subitems) {
      if ($json->r_viewmode=='objects') {
       $objects = $this->fillCustomers(clone $tmp_src_subitems, $item->subitems, $json, $userdetails);
      } else {
       $objects = $this->fillObjects  (clone $tmp_src_subitems, $item->subitems, $json, $userdetails);
      }
      $loop_table_row->fill("%objects%",  $objects->output());
     }
     
     $loop_table_row->fill("%id%"           , $item->ID             );
     $loop_table_row->fill("%tablename%"    , $json->tablename      );
     
     $thistable.=$loop_table_row->output();
     $c++;
    }
   } else {
    /*
    $thisrow   = "";
    $thisrow.=$block_norows;
    $loop_table_row->fillloop('loop_table_cell',$thisrow);
    $loop_table_row->fill("%c%",  $c%2);
    $thistable.=$loop_table_row->output();
    */
    $tmp_src->fillloop('loop_table', $block_norows);
   }
   
   $tmp_src->removeloop('loop_table');
   
   $tmp_src->fillloop('loop_table_row',$thistable);
   
   $tmp_src->fillloop(     "block_no","" );
   $tmp_src->fillloop( "block_norows","" );
   $tmp_src->fillloop(   "block_dir0","" );
   $tmp_src->fillloop(   "block_dir1","" );
   
   
   $tmp_src->fill("%numrows%",sizeof($table));
   return $tmp_src;
  }
  
  function fillCustomers($tmp_src, $table, $json, $userdetails, $tmp_src_subitems=0) {
   $block_no         = new Template($this->viewroot, $tmp_src->returnloop('block_no'));
   $block_no=$block_no->output();
   
   $block_norows     = new Template($this->viewroot, $tmp_src->returnloop('block_norows'));
   $block_norows=$block_norows->output();
   
   $checkbox_th      = new Template($this->viewroot, $tmp_src->returnloop('checkbox_th'));
   $checkbox_td      = new Template($this->viewroot, $tmp_src->returnloop('checkbox_td'));
   
   $loop_table_row   = new Template($this->viewroot, $tmp_src->returnloop('loop_table_row'));
   
   $block_dir = array();
   $block_dir[0]     = new Template($this->viewroot, $tmp_src->returnloop('block_dir0'));
   $block_dir[1]     = new Template($this->viewroot, $tmp_src->returnloop('block_dir1'));
   
   $block_dir[0] = $block_dir[0]->output();
   $block_dir[1] = $block_dir[1]->output();
   
   $loopdata = "";
   $loopdata.=$checkbox_th->output();
   
   $cols = array();
   $cols[] = 'ID';
   $cols[] = 'Date';
   $cols[] = 'Phone';
   $cols[] = 'Market';
   $cols[] = 'DesiredRooms';
   $cols[] = 'HouseTypes';
   $cols[] = 'Districts';
   $cols[] = 'User';
   $cols[] = 'MaxCost';
   $cols[] = 'Firstname';
   $cols[] = 'Surname';
   
   $districts    = $this->model->getList('districts');
   $housetypes   = $this->model->getList('housetypes');
   $desiredrooms = $this->model->getList('desiredrooms');
   
   $emptyinfo = file_get_contents($this->viewroot.'/templates/emptyinfo.htt');
   $c = 0;
   
//           ajax_echo_r ($json);
//   ajax_echo_r ($table[1]);
   
   $thistable = "";
   if (sizeof($table)) {
    $thisidcolumnname = $json->tablename."_ID";
//          echo $thisidcolumnname."<br>";
    foreach ($table as $item) {
     $loop_table_row->reload();
     $thisrow   = "";
     $checkbox_td->reload();
     $checkbox_td->fill('%checked%' , 'checked'            );
     $checkbox_td->fill('%id%'      , $item->$thisidcolumnname );
     $thisrow.=$checkbox_td->output();
     
     $loop_table_row->fill(           "%ID%", $item->ID);
     $loop_table_row->fill( "%calldiretion%", $block_dir[$item->DirectionID]);
     
     $item->Districts = "";
     $ids = explode(";",$item->DistrictIDs);
     foreach ($ids as $id_itm) {
      if ($id_itm) {
       $id = substr($id_itm, strpos($id_itm, "_")+1);
       if ($item->Districts) $item->Districts .= ", ";
       $item->Districts .= $districts[$id]->Description;
      }
     }
     
     $item->HouseTypes = "";
     $ids = explode(";",$item->HouseTypeIDs);
     foreach ($ids as $id_itm) {
      if ($id_itm) {
       $id = substr($id_itm, strpos($id_itm, "_")+1);
       if ($id) {
        if ($item->HouseTypes) $item->HouseTypes .= ", ";
        $item->HouseTypes .= $housetypes[$id]->Description;
       }
      }
     }
     
     $item->DesiredRooms = "";
     $ids = explode(";",$item->DesiredRoomsIDs);
     foreach ($ids as $id_itm) {
      if ($id_itm) {
       $id = substr($id_itm, strpos($id_itm, "_")+1);
       if ($item->DesiredRooms) $item->DesiredRooms .= ", ";
       $item->DesiredRooms .= $desiredrooms[$id]->Description;
      }
     }
     
     foreach ($cols as $cv) {
      $k=$cv;
      $v = $item->$k;
      if ($k && ($k!='CustomerID')) {
       if ($k==$json->tablename."_AuxInfo") {
        if (trim($v)=="") $v=$emptyinfo;
       }
       
       switch ($cv) {
        case ('MaxCost'):
         $loop_table_row->fill(  "%".$k."%", formatCost($v));
        break;
        default:
         $loop_table_row->fill(  "%".$k."%", (((int)$v==0) && ((string)(int)$v==$v) && ($k!='Cost'))?$block_no:($v));
        break;
       }
      }
     }
     $loop_table_row->fillloop('loop_table_cell',$thisrow);
//             $loop_table_row->fill("%c%",  ($c%2) + ((date_timestamp_get(date_create($item->DateTarget))<date_timestamp_get(date_create()) )?0:2) );
     $loop_table_row->fill("%c%",  $c%2);
     
//             echo (date_timestamp_get(date_create($item->DateTarget))."-".date_timestamp_get(date_create())."<br>");
     
     if ($tmp_src_subitems) {
      if ($json->r_viewmode=='objects') {
       $objects = $this->fillCustomers(clone $tmp_src_subitems, $item->subitems, $json, $userdetails);
      } else {
       $objects = $this->fillObjects  (clone $tmp_src_subitems, $item->subitems, $json, $userdetails);
      }
      $loop_table_row->fill("%objects%",  $objects->output());
     }
     
     $loop_table_row->fill("%id%"           , $item->ID             );
     $loop_table_row->fill("%tablename%"    , $json->tablename      );
     
     $thistable.=$loop_table_row->output();
     $c++;
    }
   } else {
    /*
    $thisrow   = "";
    $thisrow.=$block_norows;
    $loop_table_row->fillloop('loop_table_cell',$thisrow);
    $loop_table_row->fill("%c%",  $c%2);
    $thistable.=$loop_table_row->output();
    */
    $tmp_src->fillloop('loop_table', $block_norows);
   }
   
   $tmp_src->removeloop('loop_table');
   
   $tmp_src->fillloop('loop_table_row',$thistable);
   
   $tmp_src->fillloop(     "block_no","" );
   $tmp_src->fillloop( "block_norows","" );
   $tmp_src->fillloop(   "block_dir0","" );
   $tmp_src->fillloop(   "block_dir1","" );
   
   
   $tmp_src->fill("%numrows%",sizeof($table[1]));          
   
   return $tmp_src;
  }
  
  public function sms() {                                                      // the main sub in our application
   include_once("model/transport.php");       // connect to the Settings 
   
   if(!isset($_SESSION)) session_start();
   
   $action = getvariablereq('action');          // get action from the request
   $data   = getvariablereq('data'  );          // get JSON data from the request
   
   $t = $this->model->getStats('common');
   
   echo "Report date: ".date("Y-m-d H:i:s")."<br>";
   
   $phones_first  = array();	
   $phones_second = array();	
   $users=$this->model->getUsersSms();
   $this->model->resetUsersFirstSms();
   
//   ajax_echo_r ($users);
   
   foreach ($users as $user) {
    if ($user->Phone) {
     $user->Phone = str_replace("+7", "8", $user->Phone);
     $user->Phone = str_replace( "-",  "", $user->Phone);
     
     if ($user->FirstSms) {
      $phones_first[]  .= $user->Phone;
     } else {
      $phones_second[] .= $user->Phone;
     }
    }
   }
   
   $info = "Всего собственников ".$t->objects_total.", из них новых ".$t->objects_yesterday.". Всего покупателей ".$t->customers_total.", из них новых ".$t->customers_yesterday.". ";
//   $info.= "Потенциальных сделок ".$t->handshakes_auto.". ";
   $info.= "Сделок на этой неделе ".$t->handshakes_thisweek." (осталось сделать  ".($t->handshakes_thisweek_plan - $t->handshakes_thisweek).").";
   
   echo $info;
   
   $api = new Transport($this->settings);
   $params_first  = array(
    "text" => "Привет, это СМСка от Изума. ".$info
   );
   $params_second = array(
    "text" => "Доброе утро. Изум-информ сообщает: ".$info
   );
   
//   $phones_first  = array('89376411426');
//   $phones_second = array('89276047754');
   
   ajax_echo_r ($phones_first);
   ajax_echo_r ($phones_second);
   
   $send_first  = $api->send($params_first  ,$phones_first);
   $send_second = $api->send($params_second ,$phones_second);
   
   ajax_echo_r ($send_first);
   ajax_echo_r ($send_second);
   
   if ($send['code'] == 1) {
//    echo 'Отправлено '.$send['colSendAbonent'].', не отправлено';
   } else {
//    echo $send['descr'];
   }
   
   
   
  }
  
  public function backup() {
   $ret = $this->model->backup();
   
   
   
  }
  
 }
?>