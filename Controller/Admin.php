<?php

namespace Detektivo\Controller;


class Admin extends \Cockpit\AuthController {

    public function index() {

        $collections = $this->module('detektivo')->config('collections', []);

        return $this->render('detektivo:views/index.php', compact('collections'));
    }


    public function reindex() {

        $collection = $this->param('collection');

        if (!$collection) {
            return false;
        }

        $options = [
            'limit' => 100,
            'skip' => $this->param('skip', 0),
            'fields' => ['_id' => 1]
        ];

        $fields = $this->module('detektivo')->fields($collection);

        foreach ($fields as $field) {
            $root_field = explode('.', $field)[0];

            $options['fields'][$root_field] = 1;
        }

        $this->module('detektivo')->storage()->empty($collection);

        $items  = $this->module('collections')->find($collection, $options);

        $datas = [];

        foreach ($items as $item) {
            $data = [
                '_id' => $item['_id'],
            ];

            foreach ($fields as $field) {
                if ($value = $this->module('detektivo')->value($item, $field)) {
                    $data[$field] = $value;
                }
            }

            $datas[] = $data;
        }

        if (count($items)) {
            $this->module('detektivo')->storage()->batchSave($collection, $datas);
        }

        if (!count($items) || count($items) < $options['limit']) {
            return ['finished' => true, 'imported' => count($items)];
        }

        return ['finished' => false, 'imported' => count($items), 'items' => $items];
    }
}
