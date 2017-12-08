<?php

namespace Civi\Api4\Service\Spec;

class RequestSpec {

  /**
   * @var string
   */
  protected $entity;

  /**
   * @var string
   */
  protected $action;

  /**
   * @var FieldSpec[]
   */
  protected $fields = array();

  /**
   * @param string $entity
   * @param string $action
   */
  public function __construct($entity, $action) {
    $this->entity = $entity;
    $this->action = $action;
  }

  public function addFieldSpec(FieldSpec $field) {
    $this->fields[] = $field;
  }

  /**
   * @param $name
   *
   * @return FieldSpec|null
   */
  public function getFieldByName($name) {
    foreach ($this->fields as $field) {
      if ($field->getName() === $name) {
        return $field;
      }
    }

    return NULL;
  }

  /**
   * @return array
   *   Gets all the field names currently part of the specification
   */
  public function getFieldNames() {
    return array_map(function(FieldSpec $field) {
      return $field->getName();
    }, $this->fields);
  }

  /**
   * @return array|FieldSpec[]
   */
  public function getRequiredFields() {
    return array_filter($this->fields, function (FieldSpec $field) {
      return $field->isRequired();
    });
  }

  /**
   * @return FieldSpec[]
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * @return string
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * @return string
   */
  public function getAction() {
    return $this->action;
  }

}
