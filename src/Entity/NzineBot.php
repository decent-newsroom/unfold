<?php

namespace App\Entity;

use App\Service\EncryptionService;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;


#[ORM\Entity]
class NzineBot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    private ?EncryptionService $encryptionService = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $encryptedNsec = null;

    #[Ignore]
    private ?string $nsec = null;

    public function setEncryptionService(EncryptionService $encryptionService): void
    {
        $this->encryptionService = $encryptionService;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNsec(): ?string
    {
        if (null === $this->nsec && null !== $this->encryptedNsec) {
            $this->nsec = $this->encryptionService->decrypt($this->encryptedNsec);
        }
        return $this->nsec;
    }

    public function setNsec(?string $nsec): self
    {
        $this->nsec = $nsec;
        $this->encryptedNsec = $this->encryptionService->encrypt($nsec);
        return $this;
    }
}
