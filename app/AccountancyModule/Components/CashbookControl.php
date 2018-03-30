<?php

declare(strict_types=1);

namespace App\AccountancyModule\Components;

use App\AccountancyModule\Components\Cashbook\ChitListControl;
use App\AccountancyModule\Factories\Cashbook\IChitListControlFactory;
use App\AccountancyModule\Factories\IChitFormFactory;
use Model\Cashbook\Cashbook\CashbookId;

class CashbookControl extends BaseControl
{

    /** @var CashbookId */
    private $cashbookId;

    /** @var bool */
    private $isEditable;

    /** @var IChitFormFactory */
    private $formFactory;

    /** @var IChitListControlFactory */
    private $chitListFactory;

    public function __construct(CashbookId $cashbookId, bool $isEditable, IChitFormFactory $formFactory, IChitListControlFactory $chitListFactory)
    {
        parent::__construct();
        $this->cashbookId = $cashbookId;
        $this->isEditable = $isEditable;
        $this->formFactory = $formFactory;
        $this->chitListFactory = $chitListFactory;
    }

    public function render(): void
    {
        $this->template->setParameters([
            'isEditable' => $this->isEditable,
        ]);

        $this->template->setFile(__DIR__ . '/templates/CashbookControl.latte');
        $this->template->render();
    }

    protected function createComponentChitForm(): ChitForm
    {
        return $this->formFactory->create($this->cashbookId, $this->isEditable);
    }

    protected function createComponentChitList(): ChitListControl
    {
        $control = $this->chitListFactory->create($this->cashbookId, $this->isEditable);

        $control->onEditButtonClicked[] = function (int $chitId) {
            /** @var ChitForm $form */
            $form = $this['chitForm'];
            $form->editChit($chitId);

            $form->redrawControl();
        };

        return $control;
    }

}
