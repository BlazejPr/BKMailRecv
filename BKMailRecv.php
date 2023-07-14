<?php
 
namespace BKFrameWork; 
 
class BKMailRecv 
{
          public $BB_BODY_TYPES = array(
          0  => 'text',
          1  => 'multipart',
          2  => 'message',
          3  => 'application',
          4  => 'audio',
          5  => 'image',
          6  => 'video',
          7  => 'model',
          8  => 'other',
      );
      
      
      public function bb_email_fetch_from_struct($mbox, $uid,$structure = null, $part=1,$level=1, $appendString = array(), $appendAttach=array() )
      {
          try{
                 $retString     = $appendString;
                 $retAttachment = $appendAttach;
              
                 // https://www.php.net/manual/en/function.imap-fetchstructure.php
              
                 if($structure == null)
                 {
                   $structure   = imap_fetchstructure($mbox,$uid, FT_UID);                                                                      
                 }
                 
                 print_r($structure);
                 
                 
                 $type          = $structure->type;   // 0 -TYPETEXT, 1 - TYPEMULTIPART , 2 - TYPEMESSAGE, 3 - TYPEAPPLICATION, 4 - TYPEAUDIO, 5 - TYPEIMAGE, 6 - TYPEVIDEO, 7 - TYPEMODEL,  8 - TYPEOTHER
                 $encoding      = $structure->encoding; 
                 
                        
                 
                 $subtype = '';
                 if($structure->ifsubtype) $subtype = $structure->subtype;
                 
                 $disposition  = '';
                 if($structure->ifdisposition) $disposition = $structure->disposition;  //Disposition string
                 
                 $dparameters  = array();
                 if($structure->ifdparameters) $dparameters = $structure->dparameters;  //An array of objects where each object has an "attribute" and a "value" property corresponding to the parameters on the Content-disposition MIME header.
                 
                 $parameters  = array();
                 if($structure->ifparameters) $parameters = $structure->parameters;   //An array of objects where each object has an "attribute" and a "value" property.
                 
                 
                        
                        
                 
                 
                 if( $this->BB_BODY_TYPES[$type] == 'text'  && ($subtype == 'PLAIN' || $subtype == 'HTML') )   //ALTERNATIVE
                 {                        
                     $charset       = '';
                                             
                     
                     $body = imap_fetchbody($mbox, $uid, $part,   FT_UID     ) ;                                                 
                    // $bodyT = explode("\r\n\r\n", $body, 2 );                        
                     
                  //   $body = isset($bodyT[1])  ? $bodyT[1] : $bodyT[0];                        
                     
                     if($encoding == ENC7BIT) $body = imap_7bit($body);      //ENC7BIT
                     if($encoding == ENC8BIT) $body = imap_8bit($body);      //ENC8BIT
                     if($encoding == ENCBINARY) $body = imap_binary($body);    //ENCBINARY
                     if($encoding == ENCBASE64) $body = imap_base64($body);    //ENCBASE64
                     if($encoding == ENCQUOTEDPRINTABLE) $body = imap_qprint($body);    //ENCQUOTEDPRINTABLE                         
                     
                     for($px = 0; $px<count($parameters) ; $px++)
                     {
                         if($parameters[$px]->attribute == 'CHARSET') {$charset = $parameters[$px]->value; break; }
                     }
                     
                     $retString[]   = array(
                         'body'  => $body,                        
                         'charset'  => $charset,
                         'subtype'  => $subtype
                     );
                 }
                 
                 
                 if($this->BB_BODY_TYPES[$type] == 'multipart'  )  
                 {        
                     $parts       = array();
                     if($structure->parts) $parts = $structure->parts;
                     
                     for($wx =0; $wx < count($parts); $wx++)
                     {
                        echo "<br/><br/>---parse part----<br/><br/>";
                            $retX = $this->bb_email_fetch_from_struct($mbox, $uid,$parts[$wx], $wx+1,$level+1, $retString, $retAttachment);       
                            $retString      = $retX['strings'];
                            $retAttachment  = $retX['attachments'];
                        echo "<br/><br/>---end part----<br/><br/>";
                     }
                     
                 }
                 
                 if( $this->BB_BODY_TYPES[$type] == 'message' || $this->BB_BODY_TYPES[$type] == 'application'  || $this->BB_BODY_TYPES[$type] == 'audio' || $this->BB_BODY_TYPES[$type] == 'image' || $this->BB_BODY_TYPES[$type] == 'video'   || $this->BB_BODY_TYPES[$type] == 'model'  || $this->BB_BODY_TYPES[$type] == 'other' )  
                 {
                    $fileName = '';                        
                        
                    for($px = 0; $px<count($parameters) ; $px++)
                    {
                         if( strtolower($parameters[$px]->attribute) == 'name' ||  strtolower($parameters[$px]->attribute) == 'filename')
                         { $fileName = $parameters[$px]->value; break; }
                    }
                    
                    if($fileName == '')
                    {
                        for($px = 0; $px<count($dparameters) ; $px++)
                        {
                             if( strtolower($dparameters[$px]->attribute) == 'name' ||  strtolower($dparameters[$px]->attribute) == 'filename')
                             { $fileName = $dparameters[$px]->value; break; }
                        }
                    }
                    
                    $body = imap_fetchbody($mbox, $uid, $part,  FT_UID) ;    
                                       
                    if($encoding == 3) $body = base64_decode($body);  //ENCBASE64
                    if($encoding == 4) $body = quoted_printable_decode($body);  //ENCQUOTEDPRINTABLE
                     
                     
                        
                    
                    $retAttachment[] = array(
                        'filename'   => $fileName,
                        'subtype'    => $subtype,
                        'body'    => $body,                        
                    );
                    
                 }
                 
                 
               //  print_r($structure);                        
                // echo '<br/><br/>';
                 
                 
                 return array(
                     'strings' => $retString,
                     'attachments' => $retAttachment
                 );
              
             }catch(\Throwable $thr) { throw new \app\library\_PajaxException($thr, \app\library\_PajaxException::THROWABLE, $this->getEnv()); }
      }   
      //-------------------------------------------------------------
      
      public function bb_email_fetch()
      {
          try{  
                        
                $params = array();                
                $mbox = \imap_open ( "{".\app\config\ConstClass::EMAIL_HOST.":995/pop3/ssl/novalidate-cert}INBOX"   , \app\config\ConstClass::EMAIL_USERNAME,  \app\config\ConstClass::EMAIL_PASSWORD, 0,0, $params );
                                        
                if($mbox)
                { 
                    //get info. about account..
                    $MC       = imap_check($mbox);    
                    $m_data   = $MC->Date;          //current system time formatted according to » RFC2822
                    $m_driver = $MC->Driver;        //protocol used to access this mailbox: POP3, IMAP, NNTP
                    $m_name   = $MC->Mailbox;       //the mailbox name 
                    $m_all    = $MC->Nmsgs ;        //number of messages in the mailbox 
                    $m_recent = $MC->Recent;        //number of recent messages in the mailbox 
                    
                    //echo '<br/><br/>Data: '.$m_data.', Protikół: '.$m_driver.', Nazwa: '.$m_name.', Liczba wiadomości: '.$m_all.', Numer ostatniej: '.$m_recent."<br/>" ;
                    
                    
                    //Read an overview of the information in the headers of the given message
                    $result = imap_fetch_overview($mbox, "1:".$m_all, 0);
                    
                    foreach($result as $ix => $row)
                      {                     
                          $subject       = $row->subject;
                          $from          = $row->from;
                          $to            = $row->to;
                          $date          = $row->date;
                          $message_id    = $row->message_id ?? '';
                          $references    = $row-> references ?? '';
                          $size          = $row->size;
                          $uid           = $row->uid;
                          $msgno         = $row->msgno;
                          
                          
                          $from = mb_decode_mimeheader($from);
                          $to = mb_decode_mimeheader($to);
                          $subject = mb_decode_mimeheader($subject);

                          $from = str_replace('_', ' ', $from);
                          $to = str_replace('_', ' ', $to);
                          $subject = str_replace('_', ' ', $subject);
                       
                          
                         //is already exist in DB? Check..
                          $b = new \model\useclass\_PajaxQueryBuilder($this->getDaoFirst(), $this);
                          $b->setTable('adm_e_mail_sys');
                          $b->addColumn('id');                        
                          $b->whereAND('from', $from);
                          $b->whereAND('to', $to);
                          $b->whereAND('recv_time', date('Y-m-d H:i:s', strtotime($date) ) );
                          $c = $b->getCountRecord();
                          if($c > 0) { continue;}   
                          
                          
                          
                          echo "<br/><br/><br/>Subject: $subject, From: $from, To: $to, Date: $date, MessId: $message_id, Ref: $references, Size: $size, UID: $uid, MsgNO: $msgno<br/>";                                                 
                          $arr = $this->bb_email_fetch_from_struct($mbox,$uid);
                        
                       //   print_r($arr);
                          
                          $strings       = $arr['strings'];
                          $attachments   = $arr['attachments'];
                          
                    
                          
                                $status = 0;                                                         
                                $stxt = '';
                                $shtml = '';
                                
                                foreach($strings as $ixS => $strParam)
                                {
                                    if($strParam['subtype'] == 'PLAIN')
                                    {
                                        $stxt .= $strParam['body']."\r\n\r\n\r\n\r\n";
                                    }
                                    
                                    if($strParam['subtype'] == 'HTML')
                                    {
                                        $shtml .= $strParam['body']."\r\n\r\n\r\n\r\n";
                                    }
                                }
                        
                                
                                
                                //save into DB
                                $n = new \model\useclass\mysql\Base_1_adm_e_mail_sys($this->getDaoFirst(), $this);
                                $n->SetRecv_time( date('Y-m-d H:i:s', strtotime($date) ) );
                                $n->SetFrom($from);
                                $n->SetTo($to);
                                $n->SetSubject($subject);
                                $n->SetHtml_body(  $shtml );
                                $n->SetTxt_body($stxt);
                                $n->SetUid($uid);
                                $n->SetMessage_id($message_id);
                                $n->SetStatus($status);
                                $adm_e_mail_sys_id = $n->insert();

                                if($adm_e_mail_sys_id == -1)
                                {
                                  throw new \app\library\_PajaxException('Blad przy pobieraniu emaili '.print_r($n->getErrors(),true) , \app\library\_PajaxException::SQL, $this->getEnv());  
                                }
                                
                                
                                $attachPath = '/media/email/';
                                
                                foreach ($attachments as $xk => $at)
                                {
                                    if(strlen($at['filename']) ==0 )$at['filename'] = 'none.dat';
                                                                              
                                    $at['filename'] = mb_decode_mimeheader($at['filename']);                                                                          
                                       
                                       
                                       $fileRand = time().'.'.$xk.'.'.$uid.'.'.rand(9,9999).$at['filename'];
                                       
                                       file_put_contents($attachPath.$fileRand, $at['body']);
                                       
                                       if(file_exists($attachPath.$fileRand))
                                       {
                                           //save info about file into DB
                                           $a = new \model\useclass\mysql\Base_1_adm_e_mail_sys_attach($this->getDaoFirst(), $this);
                                           $a->SetFilename($at['filename']);
                                           $a->SetPath($attachPath.$fileRand);
                                           $a->SetSize( filesize( $attachPath.$fileRand ) );
                                           $a->SetAdm_e_mail_sys_id($adm_e_mail_sys_id);
                                           $aId = $a->insert();
                                           
                                           if($aId < 0 )throw new \app\library\_PajaxException('Blad przy pobieraniu emaili - zał. '.print_r($a->getErrors(),true) , \app\library\_PajaxException::SQL, $this->getEnv()); 
                                           
                                       }
                                }
                          
                      }
                    
                      
                    //close....
                    \imap_close($mbox);
                }else
                {
                    echo 'Problem z połączeniem do serwera poczty!';
                    die;
                }
              
              
          }catch(\Throwable $thr) { throw new \app\library\_PajaxException($thr, \app\library\_PajaxException::THROWABLE, $this->getEnv()); }
      }   
      //-------------------------------------------------------------
      
    

}
