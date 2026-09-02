<?php

namespace App\Service\Import\Definition;

use App\Entity\Invitation;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Service\Import\ImportChoice;
use App\Service\Import\ImportContext;
use App\Service\Import\ImportDefinitionInterface;
use App\Service\Import\ImportField;
use App\Service\InvitationService;

final class InvitationImportDefinition implements ImportDefinitionInterface
{
    public function __construct(
        private readonly MembershipRepository $membershipRepository,
        private readonly InvitationRepository $invitationRepository,
        private readonly InvitationService $invitationService,
    ) {
    }

    public function key(): string
    {
        return 'invitations';
    }

    public function titleKey(): string
    {
        return 'importInvitationsTitle';
    }

    public function listRoute(): string
    {
        return 'club_invitations';
    }

    public function previewTemplate(): string
    {
        return 'club/import/previewInvitations.html.twig';
    }

    public function fields(): array
    {
        $yesNo = [new ImportChoice('yes', 'yes', ['oui', 'yes', 'x', '1', 'vrai', 'true']), new ImportChoice('no', 'no', ['non', 'no', '0', 'faux', 'false'])];

        return [
            new ImportField('email', 'email', true, ['courriel', 'e-mail', 'email', 'mail', 'adresse']),
            new ImportField('firstname', 'firstname', true, ['prenom', 'prénom']),
            new ImportField('lastname', 'lastname', true, ['nom de famille', 'nom']),
            new ImportField('isManager', 'manager', false, ['gestionnaire', 'manager'], $yesNo, 'no'),
            new ImportField('isInspector', 'inspector', false, ['qualifie', 'inspecteur', 'part 66'], $yesNo, 'no'),
            new ImportField('isPilote', 'pilot', false, ['pilote', 'proprietaire'], $yesNo, 'no'),
        ];
    }

    public function previewFields(): array
    {
        // Les rôles sont affichés à part, en cases à cocher (voir preview_extra_cells) : pas de doublon en colonne simple.
        return array_values(array_filter($this->fields(), static fn (ImportField $field): bool => in_array($field->key, ['email', 'firstname', 'lastname'], true)));
    }

    public function validate(array $values, ImportContext $context): ?string
    {
        if (mb_strlen($values['email']) > 180 || mb_strlen($values['firstname']) > 255 || mb_strlen($values['lastname']) > 255) {
            return 'importReasonTooLong';
        }
        $email = mb_strtolower(trim($values['email']));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'importReasonInvalidEmail';
        }
        if (in_array($email, $this->existingMemberEmails($context), true)) {
            return 'importReasonAlreadyMember';
        }
        if (in_array($email, $this->pendingInvitationEmails($context), true)) {
            return 'importReasonPendingInvitation';
        }
        if (!$context->markSeen('email:' . $email)) {
            return 'importReasonDuplicateInFile';
        }

        return null;
    }

    public function import(array $values, ImportContext $context): void
    {
        $line = (int) ($values['_line'] ?? 0);
        $overrides = $context->overrides[$line] ?? null;
        // Les cases de l'écran d'aperçu priment sur le fichier ; sans formulaire (import direct), le fichier fait foi
        $role = static fn (string $key): bool => $overrides !== null ? !empty($overrides[$key]) : ($values[$key] ?? '') === 'yes';

        $invitation = $this->invitationService->createInvitation($context->club, [
            'email' => mb_strtolower(trim($values['email'])),
            'firstname' => $values['firstname'],
            'lastname' => $values['lastname'],
            'isManager' => $role('isManager'),
            'isInspector' => $role('isInspector'),
            'isPilote' => $role('isPilote'),
        ]);
        $context->created[] = $invitation;
    }

    public function finalize(ImportContext $context): void
    {
        // Les e-mails ne partent qu'après le commit de la transaction : un envoi pendant celle-ci
        // enverrait des invitations pour des lignes qu'un rollback effacerait ensuite.
        foreach ($context->created as $invitation) {
            if ($invitation instanceof Invitation) {
                $this->invitationService->sendInvitation($invitation);
            }
        }
    }

    /**
     * Adresses e-mail des membres actuels du club, préchargées en une requête pour tout l'import
     * (la mémoïsation vit sur le contexte, un par import — jamais sur la définition, un service).
     *
     * @return string[]
     */
    private function existingMemberEmails(ImportContext $context): array
    {
        return $context->remember('invitation.existingMemberEmails', fn (): array => $this->membershipRepository->findEmailsByClub($context->club));
    }

    /**
     * Adresses e-mail des invitations du club encore en attente, préchargées en une requête.
     *
     * @return string[]
     */
    private function pendingInvitationEmails(ImportContext $context): array
    {
        return $context->remember('invitation.pendingInvitationEmails', fn (): array => $this->invitationRepository->findPendingEmailsByClub($context->club));
    }
}
