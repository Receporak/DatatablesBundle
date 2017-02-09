<?php

/**
 * This file is part of the SgDatatablesBundle package.
 *
 * (c) stwe <https://github.com/stwe/DatatablesBundle>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sg\DatatablesBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Exception;
use DateTime;

/**
 * Class DatatableController
 *
 * @package Sg\DatatablesBundle\Controller
 */
class DatatableController extends Controller
{
    //-------------------------------------------------
    // Actions
    //-------------------------------------------------

    /**
     * Edit field.
     *
     * @param Request $request
     *
     * @Route("/sg/datatables/edit/field", name="sg_datatables_edit")
     * @Method("POST")
     *
     * @return Response
     * @throws Exception
     */
    public function editAction(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            // default params
            $pk = $request->request->get('pk');
            $field = $request->request->get('name');
            $value = $request->request->get('value');

            // additional params
            $entityClassName = $request->request->get('entityClassName');
            $token = $request->request->get('token');

            // check token
            if (!$this->isCsrfTokenValid('sg-datatables-editable', $token)) {
                throw new AccessDeniedException('DatatableController::editAction(): The CSRF token is invalid.');
            }

            // get an object by its primary key
            $entity = $this->getEntityByPk($entityClassName, $pk);

            // generate accessor
            $accessor = PropertyAccess::createPropertyAccessor();

            // prepare value
            $value = $this->prepareValue($entityClassName, $field, $value);

            // write value
            $accessor->setValue($entity, $field, $value);

            // save
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            return new Response('Success', 200);
        }

        return new Response('Bad request', 400);
    }

    //-------------------------------------------------
    // Helper
    //-------------------------------------------------

    /**
     * Finds an object by its primary key / identifier.
     *
     * @param string $entityClassName
     * @param mixed  $pk
     *
     * @return object
     */
    private function getEntityByPk($entityClassName, $pk)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository($entityClassName)->find($pk);
        if (!$entity) {
            throw $this->createNotFoundException('DatatableController::getEntityById(): The entity does not exist.');
        }

        return $entity;
    }

    /**
     * Prepare value.
     *
     * @param string $entityClassName
     * @param string $field
     * @param mixed  $value
     *
     * @return bool|\DateTime|float|int
     * @throws Exception
     */
    private function prepareValue($entityClassName, $field, $value)
    {
        $em = $this->getDoctrine()->getManager();
        $metadata = $em->getClassMetadata($entityClassName);

        if (false === strstr($field, '.')) {
            $fieldType = $metadata->getTypeOfField($field);
        } else {
            // @todo: unlim. associations
            $parts = explode('.', $field);
            $targetClass = $metadata->getAssociationTargetClass($parts[0]);
            $targetMeta = $em->getClassMetadata($targetClass);
            $fieldType = $targetMeta->getTypeOfField($parts[1]);
        }

        switch ($fieldType) {
            case 'datetime':
                $value = new DateTime($value);
                break;
            case 'boolean':
                $value = $this->strToBool($value);
                break;
            case 'string':
                break;
            case 'smallint':
            case 'integer':
            case 'bigint':
                $value = (int) $value;
                break;
            case 'float':
            case 'decimal':
                $value = (float) $value;
                break;
            default:
                throw new Exception("DatatableController::prepareValue(): The field type {$fieldType} is not supported.");
        }

        return $value;
    }

    /**
     * String to boolean.
     *
     * @param string $str
     *
     * @return bool
     * @throws Exception
     */
    private function strToBool($str)
    {
        if ($str === 'true') {
            return true;
        } else if ($str === 'false') {
            return false;
        } else {
            throw new Exception('DatatableController::strToBool(): Cannot convert string to boolean, expected string "true" or "false".');
        }
    }
}