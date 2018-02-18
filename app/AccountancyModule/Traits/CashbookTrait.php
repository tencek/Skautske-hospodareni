<?php

use App\Forms\BaseForm;
use Cake\Chronos\Date;
use eGen\MessageBus\Bus\CommandBus;
use Model\Auth\Resources\Camp;
use Model\Auth\Resources\Event;
use Model\Cashbook\CashbookNotFoundException;
use Model\Cashbook\ChitLockedException;
use Model\Cashbook\ChitNotFoundException;
use Model\Cashbook\Cashbook\Amount;
use Model\Cashbook\Cashbook\ChitNumber;
use Model\Cashbook\Cashbook\Recipient;
use Model\Cashbook\Commands\Cashbook\AddChitToCashbook;
use Model\Cashbook\Commands\Cashbook\UpdateChit;
use Model\Cashbook\Commands\Cashbook\RemoveChitFromCashbook;
use Model\Cashbook\ObjectType;
use Model\MemberService;
use Model\Services\PdfRenderer;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\IControl;

trait CashbookTrait
{

    /** @var \Model\EventEntity */
    protected $entityService;

    /** @var PdfRenderer */
    private $pdf;

    /** @var MemberService */
    private $memberService;

    /** @var \Nette\Utils\ArrayHash */
    protected $event;

    /** @var CommandBus */
    protected $commandBus;

    /** @var \Model\Auth\IAuthorizator */
    protected $authorizator;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    public function injectConstruct(PdfRenderer $pdf, MemberService $members): void
    {
        $this->pdf = $pdf;
        $this->memberService = $members;
    }

    /**
     * @param int $id ID of chit
     * @param int $actionId ID of event, unit or camp
     */
    public function handleRemove(int $id, int $actionId): void
    {
        $this->editableOnly();

        $cashbookId = $this->entityService->chits->getCashbookIdFromSkautisId($actionId);

        try {
            $this->commandBus->handle(new RemoveChitFromCashbook($cashbookId, $id));
            $this->flashMessage('Paragon byl smazán');
        } catch (ChitLockedException $e) {
            $this->flashMessage('Nelze smazat zamčený paragon', 'error');
        } catch (CashbookNotFoundException | ChitNotFoundException $e) {
            $this->flashMessage('Paragon se nepodařilo smazat');
        }

        if ($this->isAjax()) {
            $this->redrawControl("paragony");
            $this->redrawControl("flash");
        } else {
            $this->redirect('this', $actionId);
        }
    }

    protected function createComponentFormMass(): BaseForm
    {
        $form = new BaseForm();
        $btn = $form->addSubmit('massPrintSend');
        $btn->onClick[] = function (SubmitButton $button): void {
            $this->massPrintSubmitted($this->getSelectedChitIds($button->getForm()));
        };
        $btn = $form->addSubmit('massExportSend');
        $btn->onClick[] = function (SubmitButton $button): void {
            $this->massExportSubmitted($this->getSelectedChitIds($button->getForm()));
        };

        $form = $this->addMassMove($form);
        return $form;
    }

    /**
     * @param int[] $chitIds
     */
    private function massPrintSubmitted(array $chitIds): void
    {
        $this->redirect(':Accountancy:CashbookExport:printChits', [
            $this->getCashbookId(),
            $chitIds,
        ]);
    }

    /**
     * @param int[] $chitIds
     */
    private function massExportSubmitted(array $chitIds): void
    {
        $this->redirect(':Accountancy:CashbookExport:exportChits', [
            $this->getCashbookId(),
            $chitIds,
        ]);
    }

    /**
     * Vrací pole ID => Název pro výpravy i tábory
     * @param string $eventType "general" or "camp"
     * @param array $states
     * @return array
     */
    private function getListOfEvents(string $eventType, array $states = NULL): array
    {
        $eventService = $this->context->getService(($eventType === ObjectType::EVENT ? "event" : $eventType) . "Service")->event;
        $rawArr = $eventService->getAll(date("Y"), NULL);
        $resultArray = [];
        if (!empty($rawArr)) {
            foreach ($rawArr as $item) {
                if ($states === NULL || in_array($item['ID_Event' . ucfirst($eventType) . 'State'], $states)) {
                    $resultArray[$eventType . "_" . $item['ID']] = $item['DisplayName'];
                }
            }
        }
        return $resultArray;
    }

    private function addMassMove(BaseForm $form): BaseForm
    {
        $allItems = [
            'Výpravy' => $this->getListOfEvents(ObjectType::EVENT, ["draft"]),
            'Tábory' => $this->getListOfEvents(ObjectType::CAMP, ["draft", "approvedParent", "approvedLeader"]),
        ];
        #remove current event/camp from selectbox
        $eventType = $this->entityService->chits->type;
        unset($allItems[$eventType == ObjectType::EVENT ? 'Výpravy' : 'Tábory'][$eventType . "_" . $this->aid]);

        $form->addSelect('newEventId', 'Nová pokladní kniha:', $allItems)->setPrompt('Zvolte knihu');
        $btn = $form->addSubmit('massMoveChitsSend');
        $btn->onClick[] = function (SubmitButton $button): void {
            $this->massMoveChitsSubmitted($button);
        };
        return $form;
    }

    private function massMoveChitsSubmitted(SubmitButton $button): void
    {
        $form = $button->getForm();
        $chits = $this->getSelectedChitIds($form);
        if (empty($chits)) {
            $form->addError("Nebyly vybrány žádné paragony!");
            return;
        }
        $eventKey = $form['newEventId']->getValue();

        if ($eventKey === NULL) {
            $form->addError("Nebyla vybrána žádná cílová pokladní kniha!");
            return;
        }
        list($newType, $newEventId) = explode("_", $eventKey, 2);

        $originType = $this->entityService->chits->type;

        if($newType === ObjectType::EVENT) {
            $newEventAccessible = $this->authorizator->isAllowed(Event::UPDATE, $newEventId);
        } else {
            $newEventAccessible = $this->isCampEditable($newEventId);
        }

        if (!$this->isEditable || !$newEventAccessible) {
            $this->flashMessage("Nemáte oprávnění k původní nebo nové pokladní knize!", "danger");
            $this->redirect("this");
        }

        $this->entityService->chits->moveChits(
            $chits,
            $this->aid,
            $originType,
            $newEventId,
            $newType
        );
        $this->redirect("this");
    }

    //FORM CASHBOOK
    public function getCategoriesByType(BaseForm $form): array
    {
        return $this->entityService->chits->getCategoriesPairs($form["type"]->getValue(), $this->aid);
    }

    protected function createComponentCashbookForm(string $name): BaseForm
    {
        $form = new BaseForm();
        $this->addComponent($form, $name); // necessary for JSelect

        $form->addDatePicker("date", "Ze dne:")
            ->addRule($form::FILLED, 'Zadejte datum')
            ->getControlPrototype()->class("form-control input-sm required")->placeholder("Datum");
        $form->addText("num", "Číslo d.:")
            ->setMaxLength(5)
            ->setRequired(FALSE)
            ->addRule($form::MAX_LENGTH, 'Maximální délka čísla dokladu je %d znaků', 5)
            ->addRule(
                $form::PATTERN,
                'Číslo dokladu musí být číslo, případně číslo s prefixem až 3 velkých písmen. Pro dělené doklady můžete použít číslo za / (např. V01/1)',
                ChitNumber::PATTERN
            )
            ->getControlPrototype()->placeholder("Číslo")
            ->class("form-control input-sm");
        $form->addText("purpose", "Účel výplaty:")
            ->setMaxLength(120)
            ->addRule($form::FILLED, 'Zadejte účel výplaty')
            ->getControlPrototype()->placeholder("Účel")
            ->class("form-control input-sm required");
        $form->addSelect("type", "Typ", ["in" => "Příjmy", "out" => "Výdaje"])
            ->setAttribute("size", "2")
            ->setDefaultValue("out")
            ->addRule($form::FILLED, "Vyberte typ");
        $form->addJSelect("category", "Kategorie", $form["type"], [$this, "getCategoriesByType"])
            ->setAttribute("class", "form-control input-sm");
        $form->addText("recipient", "Vyplaceno komu:")
            ->setMaxLength(64)
            ->setHtmlId("form-recipient")
            ->getControlPrototype()->class("form-control input-sm")->placeholder("Komu/Od");
        $form->addText("price", "Částka: ")
            ->setRequired('Musíte vyplnit částku')
            ->addRule(function(IControl $control) {
                try {
                    return new Amount($control->getValue());
                } catch (InvalidArgumentException $e) {
                    return FALSE;
                }
            }, 'Částka musí být větší než 0')
            ->setMaxLength(100)
            ->setHtmlId("form-out-price")
            ->getControlPrototype()->placeholder("Částka: 2+3*15")
            ->class("form-control input-sm");
        $form->addHidden("pid");
        $form->addSubmit('send', 'Uložit')
            ->setAttribute("class", "btn btn-primary");
        $form->onSuccess[] = function (BaseForm $form): void {
            $this->cashbookFormSubmitted($form);

        };
        return $form;
    }

    /**
     * přidává paragony všech kategorií
     */
    private function cashbookFormSubmitted(BaseForm $form): void
    {
        if ($form["send"]->isSubmittedBy()) {
            $this->editableOnly();
            $values = $form->getValues();

            $cashbookId = $this->entityService->chits->getCashbookIdFromSkautisId($this->aid);

            $number = $values->num !== '' ? new ChitNumber($values->num) : NULL;
            $date = Date::instance($values->date);
            $recipient = $values->recipient !== '' ? new Recipient($values->recipient) : NULL;
            $amount = new Amount($values->price);
            $purpose = $values->purpose;
            $category = $values->category;
            try {
                if ($values['pid'] != "") {//EDIT
                    $chitId = $values['pid'];
                    unset($values['id']);

                    $this->commandBus->handle(
                        new UpdateChit($cashbookId, $chitId, $number, $date, $recipient, $amount, $purpose, $category)
                    );

                    $this->flashMessage("Paragon byl upraven.");
                } else {//ADD
                    $this->commandBus->handle(
                        new AddChitToCashbook($cashbookId, $number, $date, $recipient, $amount, $purpose, $category)
                    );

                    $this->flashMessage("Paragon byl úspěšně přidán do seznamu.");
                }
                if ($this->entityService->chits->eventIsInMinus($this->getCurrentUnitId())) {
                    $this->flashMessage("Dostali jste se do záporné hodnoty.", "danger");
                }
            } catch (InvalidArgumentException | CashbookNotFoundException $exc) {
                $this->flashMessage("Paragon se nepodařilo přidat do seznamu.", "danger");
                $this->logger->error(sprintf("Can't add chit to cashbook (%s: %s)", get_class($exc), $exc->getMessage()));
            } catch (ChitLockedException $e) {
                $this->flashMessage('Nelze upravit zamčený paragon', 'error');
            } catch (\Skautis\Wsdl\WsdlException $se) {
                $this->flashMessage("Nepodařilo se upravit záznamy ve skautisu.", "danger");
            }

            $this->redirect("default", ["aid" => $this->aid]);
        }
    }

    /**
     * ověřuje editovatelnost paragonu a případně vrací chybovou hlášku rovnou
     * @param int $chitId
     * @throws \Nette\Application\AbortException
     */
    private function isChitEditable(int $chitId): void
    {
        $chit = $this->entityService->chits->get($chitId);
        if ($chit !== FALSE && is_null($chit->lock)) {
            return;
        }
        $this->flashMessage("Paragon není možné upravovat!", "danger");
        if ($this->isAjax()) {
            $this->sendPayload();
        } else {
            $this->redirect("this");
        }
    }

    public function fillTemplateVariables(): void
    {
        $this->template->object = $this->event;
        $this->template->cashbookId = $this->getCashbookId();
        try {
            $this->template->autoCompleter = array_values($this->memberService->getCombobox(FALSE, 15));
        } catch (\Skautis\Wsdl\WsdlException $e) {
            $this->template->autoCompleter = [];
        }
    }

    private function editChit(int $chitId): void
    {
        $this->isChitEditable($chitId);
        $form = $this['cashbookForm'];
        $chit = $this->entityService->chits->get($chitId);

        $form['category']->setItems($this->entityService->chits->getCategoriesPairs($chit->ctype, $this->aid));
        $form->setDefaults([
            "pid" => $chitId,
            "date" => $chit->date->format("j. n. Y"),
            "num" => $chit->num,
            "recipient" => $chit->recipient,
            "purpose" => $chit->purpose,
            "price" => $chit->priceText,
            "type" => $chit->ctype,
            "category" => $chit->category,
        ]);
    }

    /**
     * @return int[]
     */
    private function getSelectedChitIds(\Nette\Forms\Form $form): array
    {
        $ids = $form->getHttpData(BaseForm::DATA_TEXT, 'chits[]');

        return array_map('intval', $ids);
    }

    private function isCampEditable(int $id): bool
    {
        return $this->authorizator->isAllowed(Camp::UPDATE, $id)
            || $this->authorizator->isAllowed(Camp::UPDATE_REAL, $id)
            || $this->authorizator->isAllowed(Camp::UPDATE_REAL_COST, $id);
    }

    private function getCashbookId(): int
    {
        return $this->entityService->chits->getCashbookIdFromSkautisId($this->aid);
    }
}
