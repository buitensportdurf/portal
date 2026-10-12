<?php

namespace App\Form;

use App\Entity\Event\Event;
use App\Entity\Event\RecurringEvent;
use App\Entity\Image;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ImageType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('imageFile', VichImageType::class, [
            'label' => false,
            'attr' => [
                'accept' => 'image/*'
            ]
        ]);

        // A series and its occurrences share one Image. Replacing or deleting the file in place would
        // change it for all of them (past events included) and Vich would delete the old file, so a
        // shared image gets a fresh Image instead.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) {
            $image = $event->getForm()->getData();
            $submitted = $event->getData();
            $replaces = !empty($submitted['imageFile']['file']);
            $deletes = !empty($submitted['imageFile']['delete']);

            if (!$image instanceof Image || $image->id === null || (!$replaces && !$deletes) || !$this->isShared($image)) {
                return;
            }
            $event->getForm()->setData(new Image());
            if (!$replaces) {
                // Deleting leaves an empty Image, which BaseEventType detaches from the event
                $event->setData([]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Image::class,
        ]);
    }

    private function isShared(Image $image): bool
    {
        $uses = 0;
        foreach ([Event::class, RecurringEvent::class] as $class) {
            $uses += (int) $this->em->createQueryBuilder()
                ->select('COUNT(e.id)')
                ->from($class, 'e')
                ->where('e.image = :image')
                ->setParameter('image', $image)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $uses > 1;
    }
}
