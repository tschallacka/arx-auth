<?php namespace Arx\Auth\Components;

use Auth;
use Redirect;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use RainLab\User\Models\User;
use RainLab\Forum\Models\Member;
use Exception;

class SSO extends ComponentBase
{

    public function componentDetails()
    {
        return [
            'name' => 'SSO',
            'description' => 'Include this component in your callback page.'
        ];
    }
    
    public function user()
    {
        if (!Auth::check()) {
            return null;
        }
        return Auth::getUser();
    }

    // Shamelessly stolen from https://github.com/kiu/bravecollective-mumble-sso
    // Thanks Kiu <3

    public function krand($length) {
        $alphabet = "abcdefghkmnpqrstuvwxyzABCDEFGHKMNPQRSTUVWXYZ23456789";
        $pass = "";
        for($i = 0; $i < $length; $i++) {
            $pass = $pass . substr($alphabet, hexdec(bin2hex(openssl_random_pseudo_bytes(1))) % strlen($alphabet), 1);
        }
        return $pass;
    }

    public function sstart() {
        global $_SESSION;
        session_start();
        if (!isset($_SESSION['nonce'])) {
            $_SESSION['nonce'] = $this->krand(22);
        }
    }

    public function sdestroy() {
        global $_SESSION;
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
    }
    
    public function onRun(){

        global $cfg_ccp_client_id, $cfg_ccp_client_secret, $cfg_user_agent, $_SESSION, $_GET;
    
        $cfg_ccp_client_id = "";
        $cfg_ccp_client_secret = "";
        $cfg_user_agent = "";

    /*$redirectUrl = $this->controller->pageUrl($this->property('redirect'));
        $redirectUrl = "https://arxalliance.org/central";

        $isAuthenticated = Auth::check();

        if ($isAuthenticated == false) {
            return Redirect::guest($redirectUrl);
        }

        if(empty($code)) {
            return Redirect::guest($redirectUrl);
        }*/

        global $_SESSION;
        session_start();
        if (!isset($_SESSION['nonce'])) {
            $_SESSION['nonce'] = $this->krand(22);
        }

        // ---- Translate code to token
        $sso_code = $_GET['code'];
    
        $data = http_build_query(
            array(
                'grant_type' => 'authorization_code',
                'code' => $sso_code,
            )
        );
    
        $options = array(
            'http' => array(
                'method'  => 'POST',
                'header'  => array(
                    'Authorization: Basic ' . base64_encode($cfg_ccp_client_id . ':' . $cfg_ccp_client_secret),
                    'Content-type: application/x-www-form-urlencoded',
                    'Host: login.eveonline.com',
                    'User-Agent: ' . $cfg_user_agent,
                ),
                'content' => $data,
            ),
        );

        $result = file_get_contents('https://login.eveonline.com/oauth/token', false, stream_context_create($options));

        if (!$result) {
            $_SESSION['error_code'] = 30;
            echo 'Failed to convert code to token.';
            return false;
        }

        $sso_token = json_decode($result)->access_token;

        // ---- Translate token to character

        $options = array(
            'http' => array(
                'method'  => 'GET',
                'header'  => array(
                    'Authorization: Bearer ' . $sso_token,
                    'Host: login.eveonline.com',
                    'User-Agent: ' . $cfg_user_agent,
                ),
            ),
        );
    
        $result = file_get_contents('https://login.eveonline.com/oauth/verify', false, stream_context_create($options));

        if (!$result) {
            $_SESSION['error_code'] = 40;
            echo 'Failed to convert token to character.';
            return false;
        }

        $json = json_decode($result);
        $character_id = $json->CharacterID;
        $owner_hash = $json->CharacterOwnerHash;

        // ---- Character details

        $options = array(
            'http' => array(
                'method'  => 'GET',
                'header'  => array(
                    'Host: api.eveonline.com',
                    'User-Agent: ' . $cfg_user_agent,
                ),
            ),
        );

        $result = file_get_contents('https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=' . $character_id, false, stream_context_create($options));

        if (!$result) {
            $_SESSION['error_code'] = 60;
            echo 'Failed to retrieve character details.';
            return false;
        }
    
        $apiInfo = new \SimpleXMLElement($result);
        $row = $apiInfo->result->rowset->row->attributes();

        $user = $this->user();

        $user->character_id = (string)$row->characterID;
        $user->character_name = (string)$row->characterName;
        $user->corporation_id = (int)$row->corporationID;
        $user->corporation_name = (string)$row->corporationName;
        $user->alliance_id = (int)$row->allianceID;
        $user->alliance_name = (string)$row->allianceName;

        $result = file_get_contents('https://api.eveonline.com/corp/CorporationSheet.xml.aspx?corporationid=' . (int)$row->corporationID, false, stream_context_create($options));

        if (!$result) {
            $_SESSION['error_code'] = 60;
            echo 'Failed to retrieve corporation details.';
            return false;
        }

        $apiInfo = new \SimpleXMLElement($result);
        
        $user->corporation_ticker = (string)$apiInfo->result->ticker;
        $user->display_name = '[' . (string)$apiInfo->result->ticker . ']' . ' ' . (string)$row->characterName;

        $user->save();

    if ($user->alliance_name == "Arx Alliance") {
        try {
            $user->groups()->attach(UserGroup::find(1));
            echo "Adding $user->character_name to group Arx Alliance";
            $user->save();
        }
        catch (Exception $e) {
            echo "$user->character_name is already in group Arx Alliance.";
        }
    }

    if ($user->alliance_name == "Apocalypse Now." || $user->alliance_name == "Curatores Veritatis Alliance") {
        try {
            $user->groups()->attach(UserGroup::find(2));
            echo "Adding $user->character_name to group Coalition";
            $user->save();
        }
        catch (Exception $e) {
            echo "$user->character_name is already in group Coalition.";
        }
    }

    Member::getFromUser();

    if ($user->forum_member != null) {
        $user->forum_member->username = $user->character_name;
        $user->forum_member->save();
    }

    else {
        echo "$user->character_name 's forum member not found.\n";
    }

        header('Location: ' . "https://arxalliance.org/central");

    }
}