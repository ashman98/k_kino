<?php
namespace services\actions;

use services\actions\AddNoteToCard;
use services\actions\SaveLeads;
use services\AmoCrmAuthTrite;
use services\getFromAmoCrm\GetLeadsInfoService;
use services\getFromAmoCrm\GetUsersInfoService;

class HandleWebhook
{
    use AmoCrmAuthTrite;

    private $hookData = [];

    public function setHookData(array $hookData): HandleWebhook
    {
        $this->hookData = $hookData;
        return $this;
    }

    public function handle()
    {
        if (isset($this->hookData['contacts']) || isset($this->hookData['leads'])) {

//            $log = date('Y-m-d H:i:s').' start';
//            file_put_contents(dirname(dirname(__DIR__)) . '/var/logs/log.txt', $log . PHP_EOL, FILE_APPEND);

            $actionData = [
                'action_type' => 'add',
                'entity_id' => 0,
                'entity_type' => '',
                'rend_data' => []
            ];
            $note_text = '';

//            $log = json_encode($this->hookData);
//            file_put_contents(__DIR__ . '/var/logs/log.txt', $log . PHP_EOL, FILE_APPEND);
            if (isset($this->hookData['leads'])){
                $actionData['entity_type'] = 'leads';
                $note_text .= 'Название сделки';
//                return $this->hookData;

                if (array_key_exists('add',$this->hookData['leads'])){
                    $actionData['entity_id'] = (int)$this->hookData['leads']['add'][0]['id'];
                    $actionData['action_type'] = 'add';
                }else{
                    $actionData['entity_id'] = (int)$this->hookData['leads']['update'][0]['id'];
                    $actionData['action_type'] = 'update';
                }
//
//                $actionData['entity_id'] = 293515;


                if (!empty($actionData['entity_id'])){
                    $getLeadsInfoService = new GetLeadsInfoService();
                    $leadsInfo = $getLeadsInfoService->setLeadsID((int)$actionData['entity_id'])->getLeadsInfo();

                    if (!empty($leadsInfo)){
                        $saveLeads = new SaveLeads();
                        $saveLeads->setData($leadsInfo)->addLeads();
                        $actionData['rend_data'] = $saveLeads->getRendData();
                    }
                }
            }
//            elseif (isset($this->hookData['contacts'])){
//                $actionData['entity_type'] = 'contacts';
//                $note_text .= 'Название контакта';
//
//                if (isset($this->hookData['contacts']['add'])){
//                    $actionData = $this->hookData['contacts']['add'][0];
//                    $action = 'add';
//                }elseif (isset($this->hookData['contacts']['update'])){
//                    $actionData = $this->hookData['contacts']['update'][0];
//                    $action = 'update';
//                }
//            }

            if (empty($actionData['rend_data'])) {
                return ['error' => 'rend_date'];
            }

            print_r($actionData['rend_data']);


            if ($actionData['action_type'] === 'add') {
                $note_text .= ": " . $actionData['rend_data']['name']['val']
                    . "\n".$actionData['rend_data']['responsible_user_name']['rus'].": " . $actionData['rend_data']['responsible_user_name']['val']
                    . "\n".$actionData['rend_data']['created_at']['rus'].": " . date('Y-m-d H:i:s', $actionData['rend_data']['created_at']['val']);
            }
            else{
                    $changes = '';
                    foreach ($actionData['rend_data'] as $field => $newValue) {
                        if(boolval($newValue['is_changed'])){
                            $changes .= $newValue['rus'].": ".$newValue['val']."\n";
                        }
                    }
                $note_text .= ": "
                    . $actionData['rend_data']['name']
                    . "\nИзмененные поля:\n" . $changes
                    . "\n".$actionData['rend_data']['update_at']['rus'].": " . date('Y-m-d H:i:s', $actionData['rend_data']['update_at']);
            }


            $addNoteToCard = new AddNoteToCard;
            $addNoteToCard->setAccessToken($this->accessToken);
            $addNoteToCard->setSubdomain($this->subdomain);
            return $addNoteToCard
                ->setEntityType($actionData['entity_type'])
                ->setEntityID($actionData['entity_id'])
                ->setNoteText($note_text)
                ->addNoteToCard();
        }

        return [];
    }


}