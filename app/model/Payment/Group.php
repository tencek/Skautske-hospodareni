<?php

declare(strict_types=1);

namespace Model\Payment;

use Assert\Assertion;
use Cake\Chronos\Date;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Fmasa\DoctrineNullableEmbeddables\Annotations\Nullable;
use InvalidArgumentException;
use Model\Google\Exception\NoAccessToOAuth;
use Model\Google\OAuthId;
use Model\Payment\Group\Email;
use Model\Payment\Group\PaymentDefaults;
use Model\Payment\Group\SkautisEntity;
use Model\Payment\Group\Unit;
use Model\Payment\Services\IBankAccountAccessChecker;
use Model\Payment\Services\IOAuthAccessChecker;

use function assert;

/**
 * @ORM\Entity()
 * @ORM\Table(name="pa_group")
 */
class Group
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private int $id;

    /**
     * @ORM\OneToMany(
     *     targetEntity=Unit::class,
     *     mappedBy="group",
     *     orphanRemoval=true,
     *     cascade={"persist", "remove"},
     *     indexBy="index"
     * )
     *
     * @var Collection<int, Unit>
     */
    private Collection $units;

    /**
     * @ORM\Embedded(class=SkautisEntity::class, columnPrefix=false)
     *
     * @Nullable()
     */
    private ?SkautisEntity $object = null;

    /** @ORM\Column(type="string", length=64) */
    private string $name;

    /** @ORM\Embedded(class=PaymentDefaults::class, columnPrefix=false) */
    private PaymentDefaults $paymentDefaults;

    /** @ORM\Column(type="string", length=20) */
    private string $state = self::STATE_OPEN;

    /** @ORM\Column(type="datetime_immutable", nullable=true) */
    private ?DateTimeImmutable $createdAt = null;

    /**
     * @ORM\Embedded(class=Group\BankAccount::class, columnPrefix=false)
     *
     * @Nullable()
     */
    private ?Group\BankAccount $bankAccount = null;

    /**
     * @ORM\OneToMany(targetEntity=Email::class, mappedBy="group", cascade={"persist", "remove"}, orphanRemoval=true)
     *
     * @var Collection<int, Email>
     */
    private Collection $emails;

    /** @ORM\Column(type="oauth_id", nullable=true) */
    private ?OAuthId $oauthId;

    /** @ORM\Column(type="string", length=250) */
    private string $note = '';

    /** @ORM\Column(type="integer", nullable=true) */
    private ?int $smtpId = null;

    public const STATE_OPEN   = 'open';
    public const STATE_CLOSED = 'closed';

    /**
     * @param int[]           $unitIds
     * @param EmailTemplate[] $emails
     */
    public function __construct(
        array $unitIds,
        ?SkautisEntity $object,
        string $name,
        PaymentDefaults $paymentDefaults,
        DateTimeImmutable $createdAt,
        array $emails,
        ?OAuthId $oAuthId,
        ?BankAccount $bankAccount,
        IBankAccountAccessChecker $bankAccountAccessChecker,
        IOAuthAccessChecker $oAuthAccessChecker
    ) {
        Assertion::notEmpty($unitIds);
        $this->object          = $object;
        $this->name            = $name;
        $this->paymentDefaults = $paymentDefaults;
        $this->createdAt       = $createdAt;

        $this->emails = new ArrayCollection();
        $this->units  = new ArrayCollection();

        foreach ($unitIds as $unitId) {
            $this->units->add(new Unit($this, $unitId));
        }

        if (! isset($emails[EmailType::PAYMENT_INFO])) {
            throw new InvalidArgumentException("Required email template '" . EmailType::PAYMENT_INFO . "' is missing");
        }

        foreach ($emails as $typeKey => $template) {
            $this->updateEmail(EmailType::get($typeKey), $template);
        }

        $this->changeBankAccount($bankAccount, $bankAccountAccessChecker);
        $this->changeOAuth($oAuthId, $oAuthAccessChecker);
    }

    public function update(
        string $name,
        PaymentDefaults $paymentDefaults,
        ?OAuthId $oAuthId,
        ?BankAccount $bankAccount,
        IBankAccountAccessChecker $bankAccountAccessChecker,
        IOAuthAccessChecker $oAuthAccessChecker
    ): void {
        $this->changeBankAccount($bankAccount, $bankAccountAccessChecker);
        $this->changeOAuth($oAuthId, $oAuthAccessChecker);

        $this->name            = $name;
        $this->paymentDefaults = $paymentDefaults;
        $this->oauthId         = $oAuthId;
    }

    public function open(string $note): void
    {
        if ($this->state === self::STATE_OPEN) {
            return;
        }

        $this->state = self::STATE_OPEN;
        $this->note  = $note;
    }

    public function close(string $note): void
    {
        if ($this->state === self::STATE_CLOSED) {
            return;
        }

        $this->state = self::STATE_CLOSED;
        $this->note  = $note;
    }

    public function removeBankAccount(): void
    {
        $this->bankAccount = null;
    }

    /** @param int[] $unitIds */
    public function changeUnits(
        array $unitIds,
        IBankAccountAccessChecker $bankAccountAccessChecker,
        IOAuthAccessChecker $mailAccessChecker
    ): void {
        $this->units->clear();
        foreach ($unitIds as $unitId) {
            $this->units->add(new Unit($this, $unitId));
        }

        $bankAccount = $this->bankAccount;

        if ($bankAccount !== null && ! $bankAccountAccessChecker->allUnitsHaveAccessToBankAccount($unitIds, $bankAccount->getId())) {
            $this->bankAccount = null;
        }

        if ($this->oauthId === null || $mailAccessChecker->allUnitsHaveAccessToOAuth($unitIds, $this->oauthId)) {
            return;
        }

        $this->oauthId = null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return int[] */
    public function getUnitIds(): array
    {
        return $this->units->map(
            function (Unit $unit): int {
                return $unit->getUnitId();
            },
        )->toArray();
    }

    public function getObject(): ?SkautisEntity
    {
        return $this->object;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPaymentDefaults(): PaymentDefaults
    {
        return $this->paymentDefaults;
    }

    public function getDefaultAmount(): ?float
    {
        return $this->paymentDefaults->getAmount();
    }

    public function getDueDate(): ?Date
    {
        return $this->paymentDefaults->getDueDate();
    }

    public function getConstantSymbol(): ?int
    {
        return $this->paymentDefaults->getConstantSymbol();
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEmailTemplate(EmailType $type): ?EmailTemplate
    {
        $email = $this->getEmail($type);

        return $email !== null ? $email->getTemplate() : null;
    }

    public function isEmailEnabled(EmailType $type): bool
    {
        $email = $this->getEmail($type);

        return $email !== null && $email->isEnabled();
    }

    public function updateLastPairing(DateTimeImmutable $at): void
    {
        if ($this->bankAccount === null) {
            return;
        }

        $this->bankAccount = $this->bankAccount->updateLastPairing($at);
    }

    public function invalidateLastPairing(): void
    {
        if ($this->bankAccount === null) {
            return;
        }

        $this->bankAccount = $this->bankAccount->invalidateLastPairing();
    }

    public function getLastPairing(): ?DateTimeImmutable
    {
        return $this->bankAccount !== null ? $this->bankAccount->getLastPairing() : null;
    }

    public function getOauthId(): ?OAuthId
    {
        return $this->oauthId;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function getBankAccountId(): ?int
    {
        return $this->bankAccount !== null ? $this->bankAccount->getId() : null;
    }

    private function changeBankAccount(?BankAccount $bankAccount, IBankAccountAccessChecker $accessChecker): void
    {
        if ($bankAccount === null) {
            $this->bankAccount = null;

            return;
        }

        $unitIds = $this->getUnitIds();

        if (! $accessChecker->allUnitsHaveAccessToBankAccount($unitIds, $bankAccount->getId())) {
            throw NoAccessToBankAccount::forUnits($unitIds, $bankAccount->getId());
        }

        $this->bankAccount = Group\BankAccount::create($bankAccount->getId());
    }

    public function updateEmail(EmailType $type, EmailTemplate $template): void
    {
        $email = $this->getEmail($type);

        if ($email !== null) {
            $email->updateTemplate($template);

            return;
        }

        $this->emails->add(new Email($this, $type, $template));
    }

    public function disableEmail(EmailType $type): void
    {
        $email = $this->getEmail($type);

        if ($email === null) {
            return;
        }

        $email->disable();
    }

    private function getEmail(EmailType $type): ?Email
    {
        foreach ($this->emails as $email) {
            assert($email instanceof Email);

            if ($email->getType()->equals($type)) {
                return $email;
            }
        }

        return null;
    }

    public function resetOAuth(): void
    {
        $this->oauthId = null;
    }

    private function changeOAuth(?OAuthId $oAuthId, IOAuthAccessChecker $checker): void
    {
        $unitIds = $this->getUnitIds();

        if ($oAuthId !== null && ! $checker->allUnitsHaveAccessToOAuth($unitIds, $oAuthId)) {
            throw NoAccessToOAuth::forUnits($unitIds, $oAuthId);
        }

        $this->oauthId = $oAuthId;
    }
}
