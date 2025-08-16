<?php

namespace App\Entity;

use App\Repository\EvaluationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvaluationRepository::class)]
class Evaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $noteGlobale = null;

    #[ORM\Column(nullable: true)]
    private ?int $clarte = null;

    #[ORM\Column(nullable: true)]
    private ?int $pertinence = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $suggestion = null;

    #[ORM\ManyToOne(inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Session $session = null;

    #[ORM\ManyToOne(inversedBy: 'evaluations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNoteGlobale(): ?int
    {
        return $this->noteGlobale;
    }

    public function setNoteGlobale(?int $noteGlobale): static
    {
        $this->noteGlobale = $noteGlobale;

        return $this;
    }

    public function getClarte(): ?int
    {
        return $this->clarte;
    }

    public function setClarte(?int $clarte): static
    {
        $this->clarte = $clarte;

        return $this;
    }

    public function getPertinence(): ?int
    {
        return $this->pertinence;
    }

    public function setPertinence(?int $pertinence): static
    {
        $this->pertinence = $pertinence;

        return $this;
    }

    public function getSuggestion(): ?string
    {
        return $this->suggestion;
    }

    public function setSuggestion(?string $suggestion): static
    {
        $this->suggestion = $suggestion;

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
