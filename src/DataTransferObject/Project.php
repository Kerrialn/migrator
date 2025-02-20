<?php

namespace KerrialNewham\Migrator\DataTransferObject;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use KerrialNewham\ComposerJsonParser\Model\Composer;
use KerrialNewham\Migrator\DataValueObject\Framework;
use Symfony\Component\Finder\SplFileInfo;

final class Project
{
    private string $path;
    private null|Composer $composer;
    /**
     * @var Collection<int,Framework> $frameworks
     */
    private Collection $frameworks;

    /**
     * @var Collection<int,SplFileInfo> $projectPhpFiles
     */
    private Collection $files;
    private Transition $transition;

    public function __construct()
    {
        $this->files = new ArrayCollection();
        $this->frameworks = new ArrayCollection();
        $this->transition = new Transition();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    /**
     * @return Collection<int, SplFileInfo>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(SplFileInfo $splFileInfo): static
    {
        if (!$this->files->contains($splFileInfo)) {
            $this->files->add($splFileInfo);
        }

        return $this;
    }

    public function removeFile(SplFileInfo $splFileInfo): static
    {
        if ($this->files->contains($splFileInfo)) {
            $this->files->removeElement($splFileInfo);
        }

        return $this;
    }

    /**
     * @return Collection<int, Framework>
     */
    public function getFrameworks(): Collection
    {
        return $this->frameworks;
    }

    public function addFramework(Framework $framework): static
    {
        if (!$this->frameworks->contains($framework)) {
            $this->frameworks->add($framework);
        }

        return $this;
    }

    public function removeFramework(Framework $framework): static
    {
        if ($this->frameworks->contains($framework)) {
            $this->frameworks->removeElement($framework);
        }

        return $this;
    }

    public function getComposer(): null|Composer
    {
        return $this->composer;
    }

    public function setComposer(null|Composer $composer): void
    {
        $this->composer = $composer;
    }

    public function getTransition(): Transition
    {
        return $this->transition;
    }

    public function setTransition(Transition $transition): void
    {
        $this->transition = $transition;
    }

    public function getPrimaryFramework(): null|Framework
    {
        return $this->frameworks->isEmpty() ? null : $this->frameworks->reduce(
            fn (?Framework $carry, Framework $framework) =>
            ($carry === null || $framework->getCertainty() > $carry->getCertainty()) ? $framework : $carry
        );
    }
}
