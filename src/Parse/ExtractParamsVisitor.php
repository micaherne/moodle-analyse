<?php

namespace MoodleAnalyse\Parse;

use PhpParser\Node;

class ExtractParamsVisitor extends \PhpParser\NodeVisitorAbstract {

    public $params = [];

    private $pretty;

    public function __construct(\PhpParser\PrettyPrinterAbstract $pretty) {
        $this->pretty = $pretty;
    }

    public function beforeTraverse(array $nodes) {
        $this->params = [];
    }

    public function leaveNode(Node $node) {
        if ($node instanceof \PhpParser\Node\Expr\Assign) {

            if (!$node->var instanceof \PhpParser\Node\Expr\Variable) {
                //echo "Unsupported var type: " . get_class($node->var) . PHP_EOL;
                return;
            }

            if (!$node->expr instanceof \PhpParser\Node\Expr\FuncCall) {
                return;
            }

            $var = $node->var->name;

            if ($node->expr->name instanceof \PhpParser\Node\Name) {

                $type = $node->expr->name->getFirst();

                if ($node->expr->name->isUnqualified()
                    && in_array($type, ['optional_param', 'required_param']))  {

                        $data = (object)['type' => $type];
                        if ($type == 'optional_param') {
                            $data->default = $this->pretty->prettyPrint([$node->expr->args[1]]);
                            $data->param_type = $this->pretty->prettyPrint([$node->expr->args[2]]);
                        } else {
                            $data->param_type = $this->pretty->prettyPrint([$node->expr->args[1]]);
                        }
                        if (isset($this->params[$var]) && $this->params[$var]->type != $type) {
                            if (!isset($this->params['__different'])) {
                                $this->params['__different'] = [];
                            }
                            $this->params['__different'][] = $var;
                        }
                        $this->params[$var] = $data;

                }

            }

        }
    }
}
