<?php
/**
 * Created by PhpStorm.
 * User: ruud
 * Date: 14/04/14
 * Time: 17:46
 */

namespace Kunstmaan\NodeSearchBundle\Form;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class NodeSearchAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('boost');
    }

    public function getName()
    {
        return 'node_search';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Kunstmaan\NodeSearchBundle\Entity\NodeSearch',
        ));
    }
} 