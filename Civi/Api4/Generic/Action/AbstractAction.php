<?php
namespace Civi\Api4\Generic\Action;

use Civi\API\Exception\UnauthorizedException;
use Civi\API\Kernel;
use Civi\Api4\Generic\Result;
use Civi\Api4\Utils\ReflectionUtils;

/**
 * Base class for all api actions.
 *
 * @method $this setCheckPermissions(bool $value)
 * @method bool getCheckPermissions()
 */
abstract class AbstractAction implements \ArrayAccess {

  /**
   * Api version number; cannot be changed.
   *
   * @var int
   */
  protected $version = 4;

  /*
   * Todo: not implemented.
   *
   * @var array
   *
  protected $chain = [];
   */

  /**
   * Whether to enforce acl permissions based on the current user.
   *
   * Setting to FALSE will disable permission checks and override ACLs.
   * In REST/javascript this cannot be disabled.
   *
   * @var bool
   */
  protected $checkPermissions = TRUE;

  /* @var string */
  private $entity;

  /* @var \ReflectionClass */
  private $thisReflection;

  /* @var array */
  private $thisParamInfo;

  /* @var array */
  private $thisArrayStorage;

  /**
   * Action constructor.
   * @param string $entity
   */
  public function __construct($entity = NULL) {
    // For generic actions we need the entity passed explicitly
    if ($entity) {
      $this->entity = $entity;
    }
    // For entity-specific actions we can figure out the entity from the namespace
    else {
      $namespace = substr(get_class($this), 0, strrpos(get_class($this), '\\'));
      $this->entity = substr($namespace, strrpos($namespace, '\\') + 1);
    }
    $this->thisReflection = new \ReflectionClass($this);
  }

  /**
   * Strictly enforce api parameters
   * @param $name
   * @param $value
   * @throws \Exception
   */
  public function __set($name, $value) {
    throw new \API_Exception('Unknown api parameter');
  }

  /**
   * @param int $val
   * @return $this
   * @throws \API_Exception
   */
  public function setVersion($val) {
    if ($val != 4) {
      throw new \API_Exception('Cannot modify api version');
    }
    return $this;
  }

  /**
   * Magic function to provide addFoo, getFoo and setFoo for params.
   *
   * @param $name
   * @param $arguments
   * @return static|mixed
   * @throws \API_Exception
   */
  public function __call($name, $arguments) {
    $param = lcfirst(substr($name, 3));
    $mode = substr($name, 0, 3);
    // Handle plural when adding to e.g. $values with "addValue" method.
    if ($mode == 'add' && $this->paramExists($param . 's')) {
      $param .= 's';
    }
    if ($this->paramExists($param)) {
      switch ($mode) {
        case 'get':
          return $this->$param;

        case 'set':
          if (is_array($this->$param)) {
            // Don't overwrite any defaults
            $this->$param = $arguments[0] + $this->$param;
          }
          else {
            $this->$param = $arguments[0];
          }
          return $this;

        case 'add':
          if (!is_array($this->$param)) {
            throw new \API_Exception('Cannot add to non-array param');
          }
          if (array_key_exists(1, $arguments)) {
            $this->{$param}[$arguments[0]] = $arguments[1];
          }
          else {
            $this->{$param}[] = $arguments[0];
          }
          return $this;
      }
    }
    throw new \API_Exception('Unknown api parameter: ' . $name);
  }

  /**
   * Invoke api call.
   *
   * At this point all the params have been sent in and we initiate the api call & return the result.
   * This is basically the outer wrapper for api v4.
   *
   * @return Result|array
   * @throws UnauthorizedException
   */
  final public function execute() {
    /** @var Kernel $kernel */
    $kernel = \Civi::service('civi_api_kernel');

    return $kernel->runRequest($this);
  }

  /**
   * @param \Civi\Api4\Generic\Result $result
   */
  abstract public function _run(Result $result);

  /**
   * Serialize this object's params into an array
   * @return array
   */
  public function getParams() {
    $params = [];
    foreach ($this->thisReflection->getProperties(\ReflectionProperty::IS_PROTECTED) as $property) {
      $name = $property->getName();
      $params[$name] = $this->$name;
    }
    return $params;
  }

  /**
   * Get documentation for one or all params
   *
   * @param string $param
   * @return array of arrays [description, type, default, (comment)]
   */
  public function getParamInfo($param = NULL) {
    if (!isset($this->thisParamInfo)) {
      $defaults = $this->getParamDefaults();
      foreach ($this->thisReflection->getProperties(\ReflectionProperty::IS_PROTECTED) as $property) {
        $name = $property->getName();
        if ($name != 'version') {
          $this->thisParamInfo[$name] = ReflectionUtils::getCodeDocs($property, 'Property');
          $this->thisParamInfo[$name]['default'] = $defaults[$name];
        }
      }
    }
    return $param ? $this->thisParamInfo[$param] : $this->thisParamInfo;
  }

  /**
   * @return string
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   *
   * @return string
   */
  public function getAction() {
    $name = get_class($this);
    return lcfirst(substr($name, strrpos($name, '\\') + 1));
  }

  /**
   * @param string $param
   * @return bool
   */
  protected function paramExists($param) {
    return array_key_exists($param, $this->getParams());
  }

  /**
   * @return array
   */
  protected function getParamDefaults() {
    return array_intersect_key($this->thisReflection->getDefaultProperties(), $this->getParams());
  }

  /**
   * @inheritDoc
   */
  public function offsetExists($offset) {
    return in_array($offset, ['entity', 'action', 'params', 'version', 'check_permissions']) || isset($this->thisArrayStorage[$offset]);
  }

  /**
   * @inheritDoc
   */
  public function &offsetGet($offset) {
    $val = NULL;
    if (in_array($offset, ['entity', 'action', 'params', 'version'])) {
      $getter = 'get' . ucfirst($offset);
      $val = $this->$getter();
      return $val;
    }
    if ($offset == 'check_permissions') {
      return $this->checkPermissions;
    }
    if (isset ($this->thisArrayStorage[$offset])) {
      return $this->thisArrayStorage[$offset];
    }
    else {
      return $val;
    }
  }

  /**
   * @inheritDoc
   */
  public function offsetSet($offset, $value) {
    if (in_array($offset, ['entity', 'action', 'params', 'version'])) {
      throw new \API_Exception('Cannot modify api4 state via array access');
    }
    if ($offset == 'check_permissions') {
      $this->setCheckPermissions($value);
    }
    else {
      $this->thisArrayStorage[$offset] = $value;
    }
  }

  /**
   * @inheritDoc
   */
  public function offsetUnset($offset) {
    if (in_array($offset, ['entity', 'action', 'params', 'check_permissions', 'version'])) {
      throw new \API_Exception('Cannot modify api4 state via array access');
    }
    unset($this->thisArrayStorage[$offset]);
  }

  /**
   * Is this api call permitted?
   *
   * This function is called if checkPermissions is set to true.
   *
   * @return bool
   */
  public function isAuthorized() {
    $permissions = $this->getPermissions();
    return \CRM_Core_Permission::check($permissions);
  }

  public function getPermissions() {
    $permissions = call_user_func(["\\Civi\\Api4\\" . $this->entity, 'permissions']);
    $permissions += [
      // applies to getFields, getActions, etc.
      'meta' => ['access CiviCRM'],
      // catch-all, applies to create, get, delete, etc.
      'default' => ['administer CiviCRM'],
    ];
    $action = $this->getAction();
    if (isset($permissions[$action])) {
      return $permissions[$action];
    }
    elseif (in_array($action, ['getActions', 'getFields'])) {
      return $permissions['meta'];
    }
    return $permissions['default'];
  }

}
