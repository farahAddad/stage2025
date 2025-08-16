<?php

namespace App\Entity;

use App\Repository\InscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InscriptionRepository::class)]
class Inscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'inscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'inscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateValidation = null;

    #[ORM\Column(nullable: true)]
    private ?bool $evaluationsEnvoyee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statutParticipation = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
        return $this;
    }

    public function getDateValidation(): ?\DateTime
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTime $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    public function isEvaluationsEnvoyee(): ?bool
    {
        return $this->evaluationsEnvoyee;
    }

    public function setEvaluationsEnvoyee(?bool $evaluationsEnvoyee): static
    {
        $this->evaluationsEnvoyee = $evaluationsEnvoyee;
        return $this;
    }

    public function getStatutParticipation(): ?string
    {
        return $this->statutParticipation;
    }

    public function setStatutParticipation(?string $statutParticipation): static
    {
        $this->statutParticipation = $statutParticipation;
        return $this;
    }
} 