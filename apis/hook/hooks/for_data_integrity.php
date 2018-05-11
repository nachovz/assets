<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

use BreatheCode\BCWrapper as BC;
use \AC\ACAPI;
BC::init(BREATHECODE_CLIENT_ID, BREATHECODE_CLIENT_SECRET, BREATHECODE_HOST, API_DEBUG);
BC::setToken(BREATHECODE_TOKEN);

function addDataIntegrityHooks($api){
	
	$api->post('/initialize', function (Request $request, Response $response, array $args) use ($api) {
        
        $parsedBody = $request->getParsedBody();
        $userEmail = null;
        if(isset($parsedBody['email'])) $userEmail = $parsedBody['email'];
        else if(isset($parsedBody['contact']['email'])) $userEmail = $parsedBody['contact']['email'];
        else throw new Exception('Please specify the user email');
        
         \AC\ACAPI::start(AC_API_KEY);
        $contact = \AC\ACAPI::getContactByEmail($userEmail);

        $fields = [];
        $fieldsToInitialize = [
            'REFERRAL_KEY','REFERRER_NAME','REFERRED_BY','GCLID','COMPANY_TYPE',
            'SENORITY_LEVEL','UTM_LOCATION','UTM_LANGUAGE','COURSE','PHONE',
            'PLATFORM_USERNAME','LEAD_COUNTRY','BREATHECODEID', 'ADMISSION_CODE_TEST_SCORE'
        ];
        foreach($contact->fields as $id => $field){
            
            if(in_array($field->perstag, $fieldsToInitialize))
            {
                if(empty($field->val))
                {
                    //initialize the field with undefined
                    $fields['field['.$id.','.$field->dataid.']'] = 'undefined';
                    
                    //override the initialization for language, making EN by default 
                    if($field->perstag == 'UTM_LANGUAGE')
                        $fields['field['.$id.','.$field->dataid.']'] = 'en';
                }
            }
            
        }    
        \AC\ACAPI::updateContact($contact->email,$fields);
        
        return $response->withJson('ok');
	});
	
	$api->post('/sync/breathecode_id', function (Request $request, Response $response, array $args) use ($api) {
        
        $parsedBody = $request->getParsedBody();
        $userEmail = null;
        if(isset($parsedBody['email'])) $userEmail = $parsedBody['email'];
        else if(isset($parsedBody['contact']['email'])) $userEmail = $parsedBody['contact']['email'];
        else throw new Exception('Please specify the user email');
        
        $user = BC::getUser(["user_id" => $userEmail]);

        \AC\ACAPI::start(AC_API_KEY);
        $contact = \AC\ACAPI::getContactByEmail($userEmail);

        $fields = [];
        foreach($contact->fields as $id => $field){
            if($field->perstag == 'BREATHECODEID')
            {
                $fields['field['.$id.','.$field->dataid.']'] = $user->id;
                $updatedContact = \AC\ACAPI::updateContact($contact->email,$fields);
                return $updatedContact;
            }
        }    
        
        return $response->withJson('ok');
	});
	
	$api->post('/sync/contact', function (Request $request, Response $response, array $args) use ($api) {
        
        $log = [];
        $parsedBody = $request->getParsedBody();
        
        $userEmail = null;
        if(!empty($parsedBody['email'])) $userEmail = $parsedBody['email'];
        else if(isset($parsedBody['contact']['email'])) $userEmail = $parsedBody['contact']['email'];
        else throw new Exception('Please specify the user email');
        
        ACAPI::start(AC_API_KEY);
        $contact = ACAPI::getContactByEmail($userEmail);
        /*
            is it a student or alumni?
        */
        $student = BC::getStudent(["student_id" => $userEmail]);
        if($student) //it is a student
        {
            $status = HookFunctions::studentCohortStatus($student);
            
            $newFields = [];
            if(!empty($status['lang'])) $newFields['UTM_LANGUAGE'] = $status['lang'];
            if(!empty($status['UTM_LOCATION'])) $newFields['UTM_LOCATION'] = $status['locations'];
            if(!empty($status['COURSE'])) $newFields['COURSE'] = $status['courses'];
            if(!empty($status['BREATHECODEID'])) $newFields['BREATHECODEID'] = $student->id;
            $contactFields = ACAPI::updateContactFields($contact, $newFields);
            
            if(!empty($student->phone)) $contactFields["phone"] = $student->phone;
            //apply tags for each cohort
            $contactFields["tags"] = $status['cohorts'];
            //add or remove to the active student list
            $contactFields["p[".ACAPI::list('active_student')."]"] = ACAPI::list('active_student');
            $contactFields["status[".ACAPI::list('active_student')."]"] = ($status['active']) ? 1 : 2;
            if($status['active']) $log[] = 'The student -subscribed- to the active_student list';
            else $log[] = 'The student -unsubscribed- from: active_student list';
            //add or remove to the alumni list
            $contactFields["status[".ACAPI::list('alumni')."]"] = ($status['alumni']) ? 1 : 2;
            $contactFields["p[".ACAPI::list('alumni')."]"] = ACAPI::list('alumni');
            if($status['alumni']) $log[] = 'The student -subscribed- to the alumni list';
            else $log[] = 'The student -unsubscribed- from: alumni list';
        }
        \AC\ACAPI::updateContact($contact->email,$contactFields);
        
        return $response->withJson($log);
	});

	return $api;
}