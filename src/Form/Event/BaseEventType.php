<?php

namespace App\Form\Event;

use App\Entity\Event\Tag;
use App\Form\ImageType;
use App\Form\SwitchType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;

use function Symfony\Component\Translation\t;

class BaseEventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, ['label' => t('Name')])
            ->add('description', TextareaType::class, [
                'label' => t('Description'),
                'attr' => ['rows' => 10],
                'required' => false,
            ])
            ->add('location', null, ['label' => t('Location')])
            ->add('tags', EntityType::class, [
                'label' => t('Tags'),
                'class' => Tag::class,
                'multiple' => true,
                'expanded' => true,
                'block_prefix' => 'tag_pills',
            ])
            ->add('startDate', null, ['label' => t('Start date')])
            ->add('endDate', null, ['label' => t('End date')])
            ->add('subscriberLimit', null, ['label' => t('Subscriber limit')])
            ->add('memberPrice', MoneyType::class, ['label' => t('Member price')])
            ->add('guestPrice', MoneyType::class, ['label' => t('Guest price'), 'required' => false])
            ->add('guestsAllowed', SwitchType::class, ['label' => t('Guests allowed')])
            ->add('image', ImageType::class, ['label' => t('Image'), 'required' => false]);

        // An image whose file was deleted is gone, rather than an empty Image left on the event
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (PostSubmitEvent $event) {
            $data = $event->getData();
            if ($data?->image !== null && $data->image->imageName === null && $data->image->getImageFile() === null) {
                $data->image = null;
            }
        });
    }
}
