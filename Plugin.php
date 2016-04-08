<?php
namespace Arx\Auth;

use System\Classes\PluginBase;
use Arx\Auth\Classes\AuthManager;
use RainLab\User\Models\User as UserModel;
use RainLab\User\Controllers\Users as UsersController;

/**
 * evesso Plugin Information File
 */
class Plugin extends PluginBase
{

    public $require = [
        'RainLab.User',
        'RainLab.Forum',
    ];

    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'Arx Auth',
            'description' => 'Authenticates users through EveSSO',
            'author'      => 'Arx Alliance',
            'icon'        => 'icon-leaf'
        ];
    }

    public function boot()
    {

        UsersController::extendListColumns(function($list, $model){
            
            if (!$model instanceof UserModel)
                return;

            $list->addColumns([

                'display_name' => [
                    'label' => 'Character',
                ],
                'corporation_name' => [
                    'label' => 'Corporation',
                ],
                'alliance_name' => [
                    'label' => 'Alliance',
                ],
            
            ]);

        });

        UsersController::extendFormFields(function($form, $model, $context){

            if (!$model instanceof UserModel)
                return;

            $form->addTabFields([

                'character_name' => [
                    'label' => 'Character Name',
                    'tab' => 'EVE Online',
                ],

                'character_id' => [
                    'label' => 'Character ID',
                    'tab' => 'EVE Online',
                ],

                'corporation_name' => [
                    'label' => 'Corporation Name',
                    'tab' => 'EVE Online',
                ],

                'corporation_ticker' => [
                    'label' => 'Corporation Ticker',
                    'tab' => 'EVE Online',
                ],

                'corporation_id' => [
                    'label' => 'Corporation ID',
                    'tab' => 'EVE Online',
                ],

                'alliance_name' => [
                    'label' => 'Alliance Name',
                    'tab' => 'EVE Online',
                ],

                'alliance_id' => [
                    'label' => 'Alliance ID',
                    'tab' => 'EVE Online',
                ],

            ]); 

        });

    }

    public function registerComponents()
    {
        return [
            'Arx\Auth\Components\SSO' => 'SSO',
        ];
    }

    public function registerSchedule($schedule)
    {
        $schedule->call(function () {
            CharacterRefresher::updateCharacters();
        })->hourly();
    }

}
