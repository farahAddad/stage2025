<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $action = null;

    #[ORM\Column]
    private ?\DateTime $horodatage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $valeurAvant = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $valeurApres = null;

    #[ORM\ManyToOne(inversedBy: 'auditLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getHorodatage(): ?\DateTime
    {
        return $this->horodatage;
    }

    public function setHorodatage(\DateTime $horodatage): static
    {
        $this->horodatage = $horodatage;

        return $this;
    }

    public function getValeurAvant(): ?string
    {
        return $this->valeurAvant;
    }

    public function setValeurAvant(?string $valeurAvant): static
    {
        $this->valeurAvant = $valeurAvant;

        return $this;
    }

    public function getValeurApres(): ?string
    {
        return $this->valeurApres;
    }

    public function setValeurApres(?string $valeurApres): static
    {
        $this->valeurApres = $valeurApres;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }


}
