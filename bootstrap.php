<?php

include(__DIR__.'/vendor/autoload.php');


$app->module('detektivo')->extend([

    'config' => function($key = null, $default = null) {

        static $config;

        if (!$config) {
            $config = $this->app->retrieve('config/detektivo');
        }

        if ($key) {
            return isset($config[$key]) ? $config[$key] : $default;
        }

        return $config;
    },

    'fields' => function($collection) {

        static $collections;

        if (!$collections) {
            $collections = $this->config('collections', []);
        }

        if (isset($collections[$collection])) {

            if ($collections[$collection] == '*') {
                $_collection = $this->app->module('collections')->collection($collection);

                $fields = array_keys($_collection['fields']);
            } else {
                $fields = $collections[$collection];
            }

            return $fields;
        }

        return null;
    },

    'value' => function ($entry, $field) {
        $keys = explode('.', $field);

        $walk = function ($value, $keys) use (&$walk) {
            $key = array_shift($keys);

            if ($key === '@each') {
                if (!is_array($value)) {
                    return null;
                }

                $values = array_map(function($value) use ($walk, $keys) {
                    return $walk($value, $keys);
                }, $value);

                $values = array_filter($values);

                $value = implode(' ', $values);

                return $value;
            }

            if (!isset($value[$key])) {
                return null;
            }

            $value = $value[$key];

            if ($keys) {
                return $walk($value, $keys);
            }

            return $value;
        };

        $value = $walk($entry, $keys);

        return $value;
    },

    'storage' => function() {

        static $storage;

        if (!$storage) {

            if ($config = $this->config()) {
                $storage = \Detektivo\Storage\Manager::getStorage($config);
            }
        }

        return $storage;
    }

]);

$app->on('cockpit.bootstrap', function() {

    $this->on('collections.removecollection', function($collection) {

        $collections = $this->module('detektivo')->config('collections', []);

        if (isset($collections[$collection])) {
            $this->module('detektivo')->storage()->deleteIndex($collection);
        }
    });

    $this->on('collections.save.after', function($collection, $entry, $isUpdate) {

        if ($fields = $this->module('detektivo')->fields($collection)) {

            $data = ['_id' => $entry['_id']];

            foreach ($fields as $field) {
                if ($value = $this->module('detektivo')->value($entry, $field)) {
                    $data[$field] = $value;
                }
            }

            if (count($data)) {
                $ret = $this->module('detektivo')->storage()->save($collection, $data);
            }
        }
    });

    $this->on('collections.remove.before', function($collection, $criteria) {

        $collections = $this->module('detektivo')->config('collections', []);

        if (!isset($collections[$collection])) return;

        $entries = $this->module('collections')->find($collection, [
            'fields' => ['_id' => true],
            'filter' => $criteria
        ]);

        if (!count($entries)) return;

        $ids = [];
        foreach ($entries as $entry) $ids[] = $entry['_id'];

        $this->module('detektivo')->storage()->delete($collection, $ids);
    });
});


// ADMIN
if (COCKPIT_ADMIN && !COCKPIT_API_REQUEST) {

    include_once(__DIR__.'/admin.php');
}

// REST
if (COCKPIT_API_REQUEST) {

    $app->on('cockpit.rest.init', function($routes) {
        $routes['detektivo'] = 'Detektivo\\Controller\\RestApi';
    });
}
