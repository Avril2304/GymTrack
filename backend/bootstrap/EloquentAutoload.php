<?php

// Autoloader Standalone de Eloquent ORM & Capsule Manager para GymTrack (Zero Dependencies)

namespace {
    spl_autoload_register(function ($class) {
        if (strpos($class, 'App\\') === 0) {
            $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    });
}

namespace Illuminate\Http {
    class Request {
        protected $data;
        public function __construct($data = []) {
            $rawInput = file_get_contents('php://input');
            $jsonInput = json_decode($rawInput, true);
            $parsedJson = is_array($jsonInput) ? $jsonInput : [];
            $this->data = array_merge($_GET, $_POST, $parsedJson, $data);
        }
        public function input($key, $default = null) {
            return $this->data[$key] ?? $default;
        }
        public function query($key, $default = null) {
            return $_GET[$key] ?? $this->data[$key] ?? $default;
        }
        public function only($keys) {
            $result = [];
            foreach ((array)$keys as $k) {
                if (array_key_exists($k, $this->data)) {
                    $result[$k] = $this->data[$k];
                }
            }
            return $result;
        }
        public function hasFile($key) {
            return isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
        }
        public function file($key) {
            return new class($_FILES[$key] ?? []) {
                protected $file;
                public function __construct($f) { $this->file = $f; }
                public function getClientOriginalName() { return $this->file['name'] ?? 'file'; }
                public function move($dir, $name) {
                    if (!file_exists($dir)) @mkdir($dir, 0755, true);
                    return move_uploaded_file($this->file['tmp_name'], $dir . '/' . $name);
                }
            };
        }
    }
}

namespace Illuminate\Support\Facades {
    class Route {
        public static function get($uri, $action) {}
        public static function post($uri, $action) {}
    }
}

namespace Illuminate\Database\Capsule {
    use PDO;
    class Manager {
        protected static $pdo = null;
        public function addConnection($config) {
            $host = $config['host'];
            $db = $config['database'];
            $user = $config['username'];
            $pass = $config['password'];
            $charset = $config['charset'] ?? 'utf8mb4';
            
            $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
            ];
            
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }
        public function setAsGlobal() {}
        public function bootEloquent() {}
        public static function getPdo() { return self::$pdo; }
        public static function connection() {
            return new class {
                public function getPdo() { return Manager::getPdo(); }
            };
        }
    }
}

namespace Illuminate\Database\Eloquent {
    use Illuminate\Database\Capsule\Manager as Capsule;
    use PDO;

    #[\AllowDynamicProperties]
    abstract class Model implements \JsonSerializable {
        protected $table;
        protected $primaryKey = 'id';
        public $timestamps = true;
        protected $fillable = [];
        protected $hidden = [];
        protected $attributes = [];
        protected $relations = [];
        protected $exists = false;

        public function __construct($attributes = []) {
            $this->fill($attributes);
        }

        public static function create($attributes = []) {
            $model = new static($attributes);
            $model->save();
            return $model;
        }

        public function fill($attributes) {
            foreach ($attributes as $key => $val) {
                if (empty($this->fillable) || in_array($key, $this->fillable) || $key === $this->primaryKey) {
                    $this->attributes[$key] = $val;
                }
            }
            return $this;
        }

        public function __get($key) {
            if (array_key_exists($key, $this->relations)) {
                return $this->relations[$key];
            }
            if (method_exists($this, $key)) {
                $relation = $this->$key();
                if ($relation instanceof Relation) {
                    $res = $relation->getResults();
                    $this->relations[$key] = $res;
                    return $res;
                }
            }
            return $this->attributes[$key] ?? null;
        }

        public function __set($key, $value) {
            $this->attributes[$key] = $value;
        }

        public function getTable() {
            if (!$this->table) {
                $class = (new \ReflectionClass($this))->getShortName();
                return strtolower($class);
            }
            return $this->table;
        }

        public function getKeyName() { return $this->primaryKey; }
        public function getKey() { return $this->attributes[$this->primaryKey] ?? null; }

        public static function query() {
            $instance = new static;
            return new Builder($instance);
        }

        public static function __callStatic($method, $parameters) {
            if ($method === 'create') {
                return static::create(...$parameters);
            }
            return static::query()->$method(...$parameters);
        }

        public function save() {
            $pdo = Capsule::getPdo();
            $table = $this->getTable();
            $pk = $this->getKeyName();

            if ($this->exists && isset($this->attributes[$pk])) {
                $sets = [];
                $params = [];
                foreach ($this->attributes as $k => $v) {
                    if ($k !== $pk) {
                        $sets[] = "`{$k}` = ?";
                        $params[] = $v;
                    }
                }
                $params[] = $this->attributes[$pk];
                $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE `{$pk}` = ?";
                $stmt = $pdo->prepare($sql);
                return $stmt->execute($params);
            } else {
                $cols = array_keys($this->attributes);
                $placeholders = array_fill(0, count($cols), '?');
                $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $res = $stmt->execute(array_values($this->attributes));
                if ($res && !$this->getKey()) {
                    $this->attributes[$pk] = $pdo->lastInsertId();
                }
                $this->exists = true;
                return $res;
            }
        }

        public function update($attributes = []) {
            $this->fill($attributes);
            return $this->save();
        }

        public function delete() {
            $pdo = Capsule::getPdo();
            $table = $this->getTable();
            $pk = $this->getKeyName();
            $sql = "DELETE FROM `{$table}` WHERE `{$pk}` = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$this->getKey()]);
        }

        public function toArray() {
            $arr = is_array($this->attributes) ? $this->attributes : [];
            foreach ($this->hidden as $h) {
                unset($arr[$h]);
            }
            foreach ($this->relations as $k => $rel) {
                if (is_array($rel) || $rel instanceof Collection) {
                    $arr[$k] = json_decode(json_encode($rel), true);
                } elseif ($rel instanceof Model) {
                    $arr[$k] = $rel->toArray();
                }
            }
            return $arr;
        }

        public function jsonSerialize(): mixed { return $this->toArray(); }

        public function belongsTo($related, $foreignKey = null, $ownerKey = null) {
            return new BelongsTo($this, new $related, $foreignKey, $ownerKey);
        }

        public function hasMany($related, $foreignKey = null, $localKey = null) {
            return new HasMany($this, new $related, $foreignKey, $localKey);
        }

        public function belongsToMany($related, $table = null, $foreignKey = null, $relatedKey = null) {
            return new BelongsToMany($this, new $related, $table, $foreignKey, $relatedKey);
        }

        public function setRawAttributes($attrs, $exists = true) {
            $this->attributes = $attrs;
            $this->exists = $exists;
            return $this;
        }
    }

    class Collection implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable {
        protected $items = [];
        public function __construct($items = []) { $this->items = array_values($items); }
        public function count(): int { return count($this->items); }
        public function getIterator(): \Traversable { return new \ArrayIterator($this->items); }
        public function offsetExists($offset): bool { return isset($this->items[$offset]); }
        public function offsetGet($offset): mixed { return $this->items[$offset] ?? null; }
        public function offsetSet($offset, $value): void { $this->items[$offset] = $value; }
        public function offsetUnset($offset): void { unset($this->items[$offset]); }
        public function jsonSerialize(): mixed { return array_map(function($i) { return is_object($i) && method_exists($i, 'toArray') ? $i->toArray() : $i; }, $this->items); }
        public function map($callback) { return new Collection(array_map($callback, $this->items)); }
        public function pluck($key) {
            $res = [];
            foreach ($this->items as $i) {
                $val = is_object($i) ? ($i->$key ?? null) : ($i[$key] ?? null);
                if ($val !== null) $res[] = $val;
            }
            return $res;
        }
        public function isEmpty(): bool { return empty($this->items); }
        public function isNotEmpty(): bool { return !empty($this->items); }
        public function first() { return $this->items[0] ?? null; }
        public function last() { return !empty($this->items) ? end($this->items) : null; }
        public function slice($offset, $length = null) { return new Collection(array_slice($this->items, $offset, $length)); }
        public function toArray() {
            return array_map(function($i) { return is_object($i) && method_exists($i, 'toArray') ? $i->toArray() : $i; }, $this->items);
        }
    }

    abstract class Relation {
        protected $parent;
        protected $related;
        public function __construct($parent, $related) {
            $this->parent = $parent;
            $this->related = $related;
        }
        abstract public function getResults();
    }

    class BelongsTo extends Relation {
        protected $foreignKey;
        protected $ownerKey;
        public function __construct($parent, $related, $fk, $ok) {
            parent::__construct($parent, $related);
            $this->foreignKey = $fk ?: strtolower((new \ReflectionClass($related))->getShortName()) . '_id';
            $this->ownerKey = $ok ?: $related->getKeyName();
        }
        public function getResults() {
            $val = $this->parent->{$this->foreignKey};
            if (!$val) return null;
            return $this->related::where($this->ownerKey, $val)->first();
        }
    }

    class HasMany extends Relation {
        protected $foreignKey;
        protected $localKey;
        public function __construct($parent, $related, $fk, $lk) {
            parent::__construct($parent, $related);
            $this->foreignKey = $fk ?: strtolower((new \ReflectionClass($parent))->getShortName()) . '_id';
            $this->localKey = $lk ?: $parent->getKeyName();
        }
        public function getResults() {
            $val = $this->parent->{$this->localKey};
            if (!$val) return new Collection();
            return $this->related::where($this->foreignKey, $val)->get();
        }
    }

    class BelongsToMany extends Relation {
        protected $pivotTable;
        protected $foreignKey;
        protected $relatedKey;
        public function __construct($parent, $related, $table, $fk, $rk) {
            parent::__construct($parent, $related);
            $this->pivotTable = $table;
            $this->foreignKey = $fk;
            $this->relatedKey = $rk;
        }
        public function withPivot(...$columns) {
            return $this;
        }
        public function getResults() {
            $pdo = Capsule::getPdo();
            $val = $this->parent->getKey();
            $relTable = $this->related->getTable();
            $relPk = $this->related->getKeyName();
            
            $sql = "SELECT r.* FROM `{$relTable}` r 
                    JOIN `{$this->pivotTable}` p ON p.`{$this->relatedKey}` = r.`{$relPk}`
                    WHERE p.`{$this->foreignKey}` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$val]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $models = [];
            foreach ($rows as $row) {
                $m = new $this->related;
                $models[] = $m->setRawAttributes($row);
            }
            return new Collection($models);
        }
    }

    class Builder {
        protected $model;
        protected $wheres = [];
        protected $orders = [];
        protected $withs = [];
        protected $limit = null;
        protected $columns = ['*'];

        public function __construct($model) { $this->model = $model; }

        public function select($columns = ['*']) {
            $this->columns = is_array($columns) ? $columns : func_get_args();
            return $this;
        }

        public function limit($limit) {
            $this->limit = intval($limit);
            return $this;
        }

        public function take($limit) {
            return $this->limit($limit);
        }

        public function create($attributes = []) {
            $class = get_class($this->model);
            return $class::create($attributes);
        }

        public function distinct($col = null) {
            return $this;
        }

        public function where($col, $op = null, $val = null) {
            if ($col instanceof \Closure) {
                $subBuilder = new Builder($this->model);
                $col($subBuilder);
                $this->wheres[] = ['type' => 'nested', 'builder' => $subBuilder];
                return $this;
            }
            if (func_num_args() === 2) {
                $val = $op;
                $op = '=';
            }
            $this->wheres[] = ['type' => 'basic', 'column' => $col, 'operator' => $op, 'value' => $val];
            return $this;
        }

        public function whereNull($col) {
            $this->wheres[] = ['type' => 'null', 'column' => $col];
            return $this;
        }

        public function whereIn($col, $values) {
            if ($values instanceof Collection) {
                $values = $values->toArray();
            }
            $flatValues = [];
            array_walk_recursive($values, function($v) use (&$flatValues) {
                if ($v !== null) $flatValues[] = $v;
            });
            $this->wheres[] = ['type' => 'in', 'column' => $col, 'values' => array_values($flatValues)];
            return $this;
        }

        public function whereHas($relation, $callback = null) {
            $this->wheres[] = ['type' => 'has', 'relation' => $relation, 'callback' => $callback];
            return $this;
        }

        public function orWhereHas($relation, $callback = null) {
            return $this->whereHas($relation, $callback);
        }

        public function with($relations) {
            $this->withs = array_merge($this->withs, (array)$relations);
            return $this;
        }

        public function orderBy($col, $dir = 'asc') {
            $this->orders[] = ['column' => $col, 'direction' => $dir];
            return $this;
        }

        public function get() {
            $pdo = Capsule::getPdo();
            $table = $this->model->getTable();
            
            $colsStr = "*";
            if (!empty($this->columns) && $this->columns !== ['*']) {
                $escaped = array_map(function($c) {
                    return $c === '*' ? '*' : "`" . str_replace('`', '', $c) . "`";
                }, (array)$this->columns);
                $colsStr = implode(', ', $escaped);
            }
            $sql = "SELECT {$colsStr} FROM `{$table}`";
            $bindings = [];
            
            if (!empty($this->wheres)) {
                $whereStmts = [];
                foreach ($this->wheres as $w) {
                    if ($w['type'] === 'basic') {
                        $whereStmts[] = "`{$w['column']}` {$w['operator']} ?";
                        $bindings[] = $w['value'];
                    } elseif ($w['type'] === 'null') {
                        $whereStmts[] = "`{$w['column']}` IS NULL";
                    } elseif ($w['type'] === 'in') {
                        if (empty($w['values'])) {
                            $whereStmts[] = "1 = 0";
                        } else {
                            $inPlaces = implode(', ', array_fill(0, count($w['values']), '?'));
                            $whereStmts[] = "`{$w['column']}` IN ({$inPlaces})";
                            foreach ($w['values'] as $v) $bindings[] = $v;
                        }
                    }
                }
                if (!empty($whereStmts)) {
                    $sql .= " WHERE " . implode(' AND ', $whereStmts);
                }
            }

            if (!empty($this->orders)) {
                $orderStmts = [];
                foreach ($this->orders as $o) {
                    $orderStmts[] = "`{$o['column']}` {$o['direction']}";
                }
                $sql .= " ORDER BY " . implode(', ', $orderStmts);
            }

            if ($this->limit !== null && $this->limit > 0) {
                $sql .= " LIMIT " . $this->limit;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $models = [];
            foreach ($rows as $row) {
                $class = get_class($this->model);
                $m = new $class;
                $m->setRawAttributes($row);
                
                // Eager loading de relaciones
                foreach ($this->withs as $w) {
                    if (method_exists($m, $w)) {
                        $m->$w;
                    }
                }
                $models[] = $m;
            }

            return new Collection($models);
        }

        public function first() {
            $res = $this->get();
            return $res[0] ?? null;
        }

        public function find($id) {
            return $this->where($this->model->getKeyName(), $id)->first();
        }

        public function count($col = '*') {
            return count($this->get());
        }

        public function exists() {
            return $this->first() !== null;
        }

        public function delete() {
            $items = $this->get();
            foreach ($items as $item) {
                $item->delete();
            }
            return true;
        }

        public function updateOrCreate($attributes, $values = []) {
            $item = (clone $this)->where($attributes)->first();
            if ($item) {
                $item->update($values);
                return $item;
            }
            return $this->model::create(array_merge($attributes, $values));
        }

        public function pluck($col) {
            return $this->get()->pluck($col);
        }
    }
}
