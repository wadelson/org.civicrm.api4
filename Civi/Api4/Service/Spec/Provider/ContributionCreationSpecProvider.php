<?php

namespace Civi\Api4\Service\Spec\Provider;

use Civi\Api4\Action\Actions;
use Civi\Api4\Service\Spec\RequestSpec;

class ContributionCreationSpecProvider implements SpecProviderInterface {
  /**
   * @inheritdoc
   */
  public function modifySpec(RequestSpec $spec) {
    $spec->getFieldByName('financial_type_id')->setRequired(TRUE);
  }

  /**
   * @inheritdoc
   */
  public function applies($entity, $action) {
    return $entity === 'Contribution' && $action === Actions::CREATE;
  }

}
