<?php

namespace App\Service;

use App\Entity\Club;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class InvitationImportService
{
    public function __construct(
        private readonly InvitationService $invitationService,
        private readonly InvitationRepository $invitationRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Import invitations from a Givav Excel file
     */
    public function importFromFile(UploadedFile $file, Club $club): ImportResult
    {
        $result = new ImportResult();

        try {
            // Load the Excel file
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Extract header row and find column indices
            if (empty($rows)) {
                $result->addError('Le fichier est vide');
                return $result;
            }

            $headers = array_shift($rows);
            $columnMap = $this->mapColumns($headers);

            // Validate required columns exist
            if (!isset($columnMap['email']) || !isset($columnMap['firstname']) || !isset($columnMap['lastname'])) {
                $result->addError('Le fichier doit contenir les colonnes : Courriel, Prénom, Nom');
                return $result;
            }

            // Process each row
            foreach ($rows as $rowIndex => $row) {
                $rowNumber = $rowIndex + 2; // +2 because we removed header and Excel rows start at 1
                $this->processRow($row, $columnMap, $club, $result, $rowNumber);
            }
        } catch (\Exception $e) {
            $result->addError('Erreur lors de la lecture du fichier : ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Map Givav column headers to internal field names
     */
    private function mapColumns(array $headers): array
    {
        $map = [];

        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim($header));

            switch ($normalized) {
                case 'courriel':
                    $map['email'] = $index;
                    break;
                case 'prénom':
                    $map['firstname'] = $index;
                    break;
                case 'nom':
                    $map['lastname'] = $index;
                    break;
                case 'actif ?':
                    $map['active'] = $index;
                    break;
            }
        }

        return $map;
    }

    /**
     * Process a single row from the Excel file
     */
    private function processRow(array $row, array $columnMap, Club $club, ImportResult $result, int $rowNumber): void
    {
        // Extract data from row
        $email = isset($columnMap['email']) ? trim($row[$columnMap['email']] ?? '') : '';
        $firstname = isset($columnMap['firstname']) ? trim($row[$columnMap['firstname']] ?? '') : '';
        $lastname = isset($columnMap['lastname']) ? trim($row[$columnMap['lastname']] ?? '') : '';
        $active = isset($columnMap['active']) ? trim($row[$columnMap['active']] ?? '') : '';

        // Skip empty rows
        if (empty($email) && empty($firstname) && empty($lastname)) {
            return;
        }

        // Skip inactive members (if Actif ? column is present and value is FAUX)
        if (isset($columnMap['active']) && strtoupper($active) === 'FAUX') {
            $result->addSkipped($email ?: "Ligne $rowNumber", 'skipReasonInactive');
            return;
        }

        // Validate email is present
        if (empty($email)) {
            $result->addSkipped("Ligne $rowNumber", 'skipReasonMissingEmail');
            return;
        }

        // Validate email format
        $emailConstraint = new Assert\Email();
        $errors = $this->validator->validate($email, $emailConstraint);
        if (count($errors) > 0) {
            $result->addSkipped($email, 'skipReasonInvalidEmail');
            return;
        }

        // Validate firstname and lastname are both present
        if (empty($firstname) || empty($lastname)) {
            $result->addSkipped($email, 'skipReasonMissingData');
            return;
        }

        // Check if user is already a member of the club
        $existingMembership = $this->membershipRepository->findByEmailAndClub($email, $club);
        if ($existingMembership) {
            $result->addSkipped($email, 'skipReasonAlreadyMember');
            return;
        }

        // Check if pending invitation already exists
        $existingInvitation = $this->invitationRepository->findPendingByEmailAndClub($email, $club);
        if ($existingInvitation) {
            $result->addSkipped($email, 'skipReasonPendingInvitation');
            return;
        }

        // Create and send invitation
        try {
            $invitation = $this->invitationService->createInvitation($club, [
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'isManager' => false,
                'isInspector' => false,
            ]);

            $this->invitationService->sendInvitation($invitation);

            $result->addCreated($email);
        } catch (\Exception $e) {
            $result->addSkipped($email, 'Erreur : ' . $e->getMessage());
        }
    }
}

/**
 * Result object for import operations
 */
class ImportResult
{
    private array $created = [];
    private array $skipped = [];
    private array $errors = [];

    public function addCreated(string $email): void
    {
        $this->created[] = $email;
    }

    public function addSkipped(string $identifier, string $reason): void
    {
        $this->skipped[] = [
            'identifier' => $identifier,
            'reason' => $reason,
        ];
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function getCreatedCount(): int
    {
        return count($this->created);
    }

    public function getSkippedCount(): int
    {
        return count($this->skipped);
    }

    public function getCreated(): array
    {
        return $this->created;
    }

    public function getSkipped(): array
    {
        return $this->skipped;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getTotalProcessed(): int
    {
        return $this->getCreatedCount() + $this->getSkippedCount();
    }
}

