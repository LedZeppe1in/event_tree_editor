<?php

namespace app\components;

use app\modules\editor\models\Level;
use app\modules\editor\models\Node;
use app\modules\editor\models\Sequence;
use app\modules\editor\models\Parameter;
use app\modules\editor\models\TreeDiagram;

class EventTreeXMLImport
{
    public static $array_nodes;
    public static $j;

    public static function addingСhild($parent, $tree_diagram_id, $level_id, $node_id )
    {
        //$parent родительский xml элемент
        //если у родетельского xml элемента есть дочернии
        if ($parent->count() != 0){
            //получаем у родетельского xml элемента дочернии
            foreach($parent->children() as $child) {
                //проверки если имя дочернего элемента равны
                if ($child->getName() == 'Operator') {
                    self::addingСhild($child, $tree_diagram_id, $level_id, $node_id);
                }
                if ($child->getName() == 'Event'){
                    //создание Node
                    $node_model = new Node();
                    $node_model->name = (string) $child["name"];
                    $node_model->certainty_factor = (real) $child["certainty_factor"];
                    $node_model->description = (string) $child["description"];
                    $node_model->operator = Node::AND_OPERATOR;

                    if (((string) $child["type"] == "Инициирующее событие") or ((string) $child["type"] == "Initial event")){
                        $node_model->type = Node::INITIAL_EVENT_TYPE;
                    }
                    if (((string) $child["type"] == "Событие") or ((string) $child["type"] == "Event")){
                        $node_model->type = Node::EVENT_TYPE;
                    }
                    if (((string) $child["type"] == "Механизм") or ((string) $child["type"] == "Mechanism")){
                        $node_model->type = Node::MECHANISM_TYPE;
                    }

                    //нахождение parent_node из таблицы $array_nodes если родитель не определен,
                    // но присутствует значение parent_node в xml
                    if (($node_id == null) and ((integer) $child["parent_node"]) <> 0){
                        for ($i = 0; $i < self::$j; $i++) {
                             if ((integer) $child["parent_node"] == self::$array_nodes[$i]['node_template']){
                                 $node_model->parent_node = self::$array_nodes[$i]['node'];
                             }
                        }
                    } else {
                        $node_model->parent_node = $node_id;
                    }
                    $node_model->tree_diagram = $tree_diagram_id;
                    $node_model->level_id = $level_id;
                    $node_model->save();

                    //таблица $array_nodes внесение значений
                    //'node_template' значение id node из xml
                    //'node' значение нового id node из только что созданного
                    self::$array_nodes[self::$j]['node_template'] = (integer) $child["id"];
                    self::$array_nodes[self::$j]['node'] = $node_model->id;
                    self::$j = self::$j+1;

                    //создание Sequence
                    $sequence_model = new Sequence();
                    $sequence_model->tree_diagram = $tree_diagram_id;
                    $sequence_model->level = $level_id;
                    $sequence_model->node = $node_model->id;
                    $sequence_model_count = Sequence::find()->where(['tree_diagram' => $tree_diagram_id])->count();
                    $sequence_model->priority = $sequence_model_count;
                    $sequence_model->save();

                    self::addingСhild($child, $tree_diagram_id, $level_id, $node_model->id);
                }

                if ($child->getName() == 'Parameter'){
                    //создание Parameter
                    $parameter_model = new Parameter();
                    $parameter_model->name = (string) $child["name"];
                    $parameter_model->description = (string) $child["description"];
                    if ((string) $child["operator"] == "="){
                        $parameter_model->operator = Parameter::EQUALLY_OPERATOR;
                    }
                    if ((string) $child["operator"] == ">"){
                        $parameter_model->operator = Parameter::MORE_OPERATOR;
                    }
                    if ((string) $child["operator"] == "<"){
                        $parameter_model->operator = Parameter::LESS_OPERATOR;
                    }
                    if ((string) $child["operator"] == ">="){
                        $parameter_model->operator = Parameter::MORE_EQUAL_OPERATOR;
                    }
                    if ((string) $child["operator"] == "<="){
                        $parameter_model->operator = Parameter::LESS_EQUAL_OPERATOR;
                    }
                    if ((string) $child["operator"] == "≠"){
                        $parameter_model->operator = Parameter::NOT_EQUAL_OPERATOR;
                    }
                    if ((string) $child["operator"] == "≈"){
                        $parameter_model->operator = Parameter::APPROXIMATELY_EQUAL_OPERATOR;
                    }
                    $parameter_model->value = (string) $child["value"];
                    $parameter_model->node = $node_id;
                    $parameter_model->save();
                }
            }
        }
    }


    //очистка диаграммы
    public function cleaningDiagram($id){
        $level_mas = Level::find()->where(['tree_diagram' => $id])->all();
        foreach ($level_mas as $elem){
            $elem -> delete();
        }

        $node_mas = Node::find()->where(['tree_diagram' => $id])->all();
        foreach ($node_mas as $elem){
            $elem -> delete();
        }
    }



    public function importCodeXML($id, $file)
    {
        self::cleaningDiagram($id);

        //массив node (для копирования связей)
        self::$array_nodes = array();
        self::$j = 0;

        $tree_diagram_model = TreeDiagram::find()->where(['id' => $id])->one();
        $tree_diagram_model->description = (string) $file["description"];
        $tree_diagram_model->save();

        $parent_level = null;
        foreach($file->Level as $level)
        {
            $level_model = new Level();
            $level_model->name = (string) $level["name"];
            $level_model->description = (string) $level["description"];
            $level_model->parent_level = $parent_level;
            $level_model->tree_diagram = $id;
            $level_model->save();

            $parent_level = $level_model->id;

            //создание дочерних элементов
            self::addingСhild($level, $id, $parent_level, null);
        }




        //удаление файла
        //unlink('uploads/temp.xml');
    }



}