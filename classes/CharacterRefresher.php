<?php namespace Arx\Auth\Classes;

use RainLab\User\Models\User;
use Rainlab\User\Models\UserGroup;

class CharacterRefresher
{
    public static function updateCharacters()
    {
        $users = User::all();
        echo "Starting loop...\n";
        foreach ($users as $user) {
            if ($user->character_id != null) {

                // ---- Character details

                $options = array(
                    'http' => array(
                        'method'  => 'GET',
                        'header'  => array(
                            'Host: api.eveonline.com',
                            'User-Agent: ' . "Arx Alliance - Michael Mach",
                        ),
                    ),
                );

                $result = file_get_contents('https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=' . (int)$user->character_id, false, stream_context_create($options));

                if (!$result) {
                    $_SESSION['error_code'] = 60;
                    echo "Failed to retrieve character details for $user->character_name\n";
                    return false;
                }

                $apiInfo = new \SimpleXMLElement($result);
                $row = $apiInfo->result->rowset->row->attributes();

                $user->corporation_id = (int)$row->corporationID;
                $user->corporation_name = (string)$row->corporationName;
                $user->alliance_id = (int)$row->allianceID;
                $user->alliance_name = (string)$row->allianceName;

                echo "$user->character_name affiliations updated.\n";

                $result = file_get_contents('https://api.eveonline.com/corp/CorporationSheet.xml.aspx?corporationid=' . (int)$row->corporationID, false, stream_context_create($options));

                if (!$result) {
                    $_SESSION['error_code'] = 60;
                    echo "Failed to retrieve corporation details for $user->character_name\n";
                    return false;
                }

                $apiInfo = new \SimpleXMLElement($result);

                $user->corporation_ticker = (string)$apiInfo->result->ticker;
                $user->display_name = '[' . (string)$apiInfo->result->ticker . ']' . ' ' . (string)$row->characterName;

                echo "$user->character_name ticker and display name updated.\n";

                if ($user->forum_member != null) {
                    
                    $user->forum_member->username = $user->character_name;
                    $user->forum_member->save();

                    echo "$user->character_name forum name updated.\n";
                }

                else {
                    echo "$user->character_name 's forum member not found.\n";
                }

                $user->save();

                echo "$user->character_name updated.\n";

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

            }
        }
        echo "Ending loop. o7\n";
    }
}