<?php

namespace MiladZamir\Sledge\Builder;

use Illuminate\Http\JsonResponse;

class Builder
{
    private $model;
    private $modelName;
    private $table = [];
    private $actions = [];
    private $config;
    public $form;
    public $formData = [
        'header' => [],
        'body' => [],
        'footer' => []
    ];
    private $script;

    public function __construct($model)
    {
        $this->model = app($model);
        $this->modelName = $model;
    }

    public function column($name)
    {
        $this->table[] = new Column($name);
        return end($this->table);
    }

    public function config($config): Config
    {
        $this->config = new Config($config, $this->model, $this->modelName);
        return $this->config;
    }

    public function script($scriptFile)
    {
        $script = new Script($scriptFile);
        $this->script = $script->scriptFile;
    }

    public function getDataTable($request): JsonResponse
    {
        $mmx = $this->config->value->count();
        $start = (int)$request->input('start');
        $length = (int)$request->input('length');

        if ($request->search['value'] != null) {
            $data = $this->config->value;
            foreach ($this->config->searchAttributes as $key => $sv) {
                if ($key == 0) {
                    $data->where($sv, 'LIKE', "%" . ($request->search['value'] . "%"));
                } else {
                    $data->orWhere($sv, 'LIKE', "%" . ($request->search['value'] . "%"));
                }
            }
            $mmxF = $data->count();
            $data = $data->skip($start)->take($length)->get();
        } else {
            $data = $this->config->value->skip($start)->take($length)->get();
        }

        $page = ($start / $length) + 1;
        if (empty($page))
            $page = 1;

        if ($page < 0)
            $page = 1;

        $request->request->add(['page' => $page]);


        $lastD[][] = null;
        foreach ($data as $k => $dat) {
            $secData = clone $dat;
            foreach ($this->table as $key => $table) {
                if ($table == 'action'){
                    if (is_array($this->actions) && !empty($this->actions)) {
                        $routeStrings = '';
                        foreach ($this->actions as $action){
                            $routeVariables = [];
                            foreach ($action->variables as $variable) {
                                foreach ($variable as $var => $dValue) {
                                    $routeVariables[$var] = $dat->{$dValue};
                                }
                            }
                            $route = route($action->name, $routeVariables);
                            $routeString = config('sledge.columnAction.route');
                            $routeString = str_replace('*1', $action->cssClass, $routeString);
                            $routeString = str_replace('*2', $route, $routeString);
                            $routeString = str_replace('*3', $action->icon, $routeString);
                            $routeString = str_replace('*4', $action->title, $routeString);
                            $routeStrings .= $routeString;
                        }
                        $lastD[$k][$key] = str_replace('*1', $routeStrings, config('sledge.columnAction.static'));
                        continue;
                    }
                }

                $str = explode('.', $table->name);
                $count = count($str);
                if ($table->name == '#') {
                    $lastD[$k][$key] = $k + 1;
                    continue;
                }
                if ($count == 1) {
                    if (!empty($table->callBack)) {
                        $lastD[$k][$key] = $table->callBack($dat->{$str[0]})->callBack;
                        continue;
                    }
                    $lastD[$k][$key] = $dat->{$str[0]};
                    continue;
                }
                if ($count > 1) {
                    for ($i = 0; $i < count($str); $i++) {
                        $dat = $dat->{$str[$i]};
                        if ($dat == null) {
                            $lastD[$k][$key] = '-';
                            break;
                        }
                        $lastD[$k][$key] = $dat;
                    }
                    if (!empty($table->callBack)) {
                        $lastD[$k][$key] = $table->callBack($dat)->callBack;
                    }
                }
                $dat = $secData;
            }
        }
        $data1 = $lastD;

        if ($data1[0][0] == null)
            $data1 = [];

        return response()->json([
            'data' => $data1,
            "draw" => $request->input('draw'),
            "recordsTotal" => $mmx,
            "recordsFiltered" => $mmxF ?? $mmx,
        ]);
    }

    public function render(): array
    {
        if (!empty($this->form)){
            foreach ($this->form as $form){
                if (!empty($form->headerData)){
                    array_push($this->formData['header'], $form->headerData);
                }elseif (!empty(!empty($form->bodyData))){
                    array_push($this->formData['body'], $form->bodyData);
                }elseif (!empty($form->footerData)){
                    array_push($this->formData['footer'], $form->footerData);
                }
            }
        }
        if (!empty($this->table) &&  is_array($this->table)){
            $haveAction = false;
            foreach ($this->table as $key=>$table){
                if (isset($table->variables)){
                    array_push($this->actions, $table);
                    unset($this->table[$key]);
                    $haveAction = true;
                }
            }
            if ($haveAction){
                array_push($this->table, 'action');
                $this->table = array_values($this->table);
            }
        }
        return ['table' => $this->table, 'button' => $this->config->button, 'breadcrumb' => $this->config->breadcrumb, 'form' => $this->formData, 'script' => $this->script];
    }

    public function form()
    {
        $this->form[] = new Form($this->config);
        return end($this->form);
    }
/*
    public function file($name, $label, $validate = [], $value = null, $placeholder = null, $size = [], $class = null, $id = null)
    {
        $data = [
            'uniqueId' => Helper::createUniqueString(5),
            'name' => $name,
            'validate' => $validate,
            'value' => $value,
            'label' => $label,
            'placeholder' => $placeholder,
            'size' => $size,
            'class' => $class,
            'id' => $id,
        ];
        array_push($this->data['body'], view('sledge::element.file')->with('data', $data));
    }

    public function checkbox($name, $label, $dKey, $validate = [], $value, $old = null, $class = null, $id = null)
    {
        $data = [
            'uniqueId' => Helper::createUniqueString(5),
            'name' => $name,
            'label' => $label,
            'dKey' => $dKey,
            'validate' => $validate,
            'value' => $value,
            'old' => $old,
            'class' => $class,
            'id' => $id,
        ];

        array_push($this->data['body'], view('sledge::element.checkbox')->with('data', $data));
    }

    public function textarea($type, $name, $label, $validate = [], $value = null, $row = null, $placeholder = null, $class = null, $id = null)
    {
        $data = [
            'uniqueId' => Helper::createUniqueString(5),
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'validate' => $validate,//implode(" ", $validate)
            'row' => $row,
            'label' => $label,
            'placeholder' => $placeholder,
            'class' => $class,
            'id' => $id,
        ];
        array_push($this->data['body'], view('sledge::element.textarea')->with('data', $data));
    }

    public function date($name, $label, array $bind, $validate = [], $value = null, $old = null, $placeholder = null, $class = null, $id = null)
    {
        $data = [
            'uniqueId' => Helper::createUniqueString(5),
            'name' => $name,
            'label' => $label,
            'bind' => $bind,
            'validate' => $validate,
            'value' => $value,
            'old' => $old,
            'placeholder' => $placeholder,
            'class' => $class,
            'id' => $id,
        ];
        array_push($this->data['body'], view('sledge::element.datePicker')->with('data', $data));
    }

    public function holder($selector, $label, array $bind, $value = null, $old = null, $class = null, $id = null)
    {
        $data = [
            'uniqueId' => Helper::createUniqueString(5),
            'selector' => $selector,
            'label' => $label,
            'bind' => $bind,
            'value' => $value,
            'old' => $old,
            'class' => $class,
            'id' => $id,
        ];

        array_push($this->data['body'], view('sledge::element.holder')->with('data', $data));
    }

    public function customView($src, $data = null, $col = 'col-6')
    {
        array_push($this->data['body'], view('sledge::' . $src)->with(compact('data', 'col')));
    }*/

}
