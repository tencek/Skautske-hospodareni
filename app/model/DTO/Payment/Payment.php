<?php

declare(strict_types=1);

namespace Model\DTO\Payment;

use Cake\Chronos\Date;
use DateTimeImmutable;
use Model\Common\EmailAddress;
use Model\Payment\Payment\SentEmail;
use Model\Payment\Payment\State;
use Model\Payment\Payment\Transaction;
use Model\Payment\VariableSymbol;
use Nette\SmartObject;
use Nette\Utils\Strings;

use function array_map;
use function implode;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read float $amount
 * @property-read EmailAddress[] $recipients
 * @property-read Date $dueDate
 * @property-read VariableSymbol|NULL $variableSymbol
 * @property-read int|NULL $constantSymbol
 * @property-read string $note
 * @property-read bool $closed
 * @property-read State $state
 * @property-read Transaction $transaction
 * @property-read DateTimeImmutable|NULL $closedAt
 * @property-read string|NULL $closedBy
 * @property-read int|NULL $personId
 * @property-read int $groupId
 */
class Payment
{
    use SmartObject;

    private int $id;

    private string $name;

    private float $amount;

    /** @var EmailAddress[]  */
    private array $recipients;

    private Date $dueDate;

    private ?VariableSymbol $variableSymbol;

    private ?int $constantSymbol;

    private string $note;

    private bool $closed;

    private State $state;

    private ?Transaction $transaction;

    private ?DateTimeImmutable $closedAt;

    private ?string $closedByUsername;

    private ?int $personId;

    private int $groupId;

    /** @var SentEmail[] */
    private array $sentEmails;

    /**
     * @param EmailAddress[] $recipients
     * @param SentEmail[]    $sentEmails
     */
    public function __construct(
        int $id,
        string $name,
        float $amount,
        array $recipients,
        Date $dueDate,
        ?VariableSymbol $variableSymbol,
        ?int $constantSymbol,
        string $note,
        bool $closed,
        State $state,
        ?Transaction $transaction,
        ?DateTimeImmutable $closedAt,
        ?string $closedByUsername,
        ?int $personId,
        int $groupId,
        array $sentEmails
    ) {
        $this->id               = $id;
        $this->name             = $name;
        $this->amount           = $amount;
        $this->recipients       = $recipients;
        $this->dueDate          = $dueDate;
        $this->variableSymbol   = $variableSymbol;
        $this->constantSymbol   = $constantSymbol;
        $this->note             = $note;
        $this->closed           = $closed;
        $this->state            = $state;
        $this->transaction      = $transaction;
        $this->closedAt         = $closedAt;
        $this->closedByUsername = $closedByUsername;
        $this->personId         = $personId;
        $this->groupId          = $groupId;
        $this->sentEmails       = $sentEmails;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /** @return EmailAddress[] */
    public function getEmailRecipients(): array
    {
        return $this->recipients;
    }

    public function getRecipientsString(): string
    {
        return implode(', ', array_map(fn (EmailAddress $emailAddress) => Strings::truncate($emailAddress->getValue(), 35), $this->recipients));
    }

    public function getDueDate(): Date
    {
        return $this->dueDate;
    }

    public function getVariableSymbol(): ?VariableSymbol
    {
        return $this->variableSymbol;
    }

    public function getConstantSymbol(): ?int
    {
        return $this->constantSymbol;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function getState(): State
    {
        return $this->state;
    }

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function getClosedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getClosedByUsername(): ?string
    {
        return $this->closedByUsername;
    }

    public function getPersonId(): ?int
    {
        return $this->personId;
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    /** @return SentEmail[] */
    public function getSentEmails(): array
    {
        return $this->sentEmails;
    }
}
