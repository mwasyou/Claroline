<?php

namespace Claroline\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Claroline\CoreBundle\Form\SelectResourceType;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Form\DirectoryType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ResourceController extends Controller
{
    public function indexAction()
    {
        $user = $this->get('security.context')->getToken()->getUser(); 
        $formResource = $this->get('form.factory')->create(new SelectResourceType(), new ResourceType());
        $resources = $this->get('claroline.resource.manager')->getRootResourcesOfUser($user);
        $em = $this->getDoctrine()->getEntityManager();        

        return $this->render(
            'ClarolineCoreBundle:Resource:index.html.twig', array('form_resource' => $formResource->createView(), 'resources' => $resources, 'id' => null)
        );
    }
    
    public function showResourceFormAction($id)
    {
        $request = $this->get('request');
        $form = $this->get('form.factory')->create(new SelectResourceType());
        $form->bindRequest($request);

        if ($form->isValid())
        {       
            $resourceType = $form['type']->getData();
            $rsrcServName = $this->findRsrcServ($resourceType);
            $rsrcServ = $this->get($rsrcServName);
            $form = $rsrcServ->getForm();
            
            return $this->render(
                'ClarolineCoreBundle:Resource:form_page.html.twig', array('form' => $form->createView(), 'id' => $id, 'type' => $resourceType->getType())
            );
        }
        
        throw new \Exception("form error");
    }
    
    public function addAction($type, $id)
    {
        $request = $this->get('request');
        $resourceType = $this->getDoctrine()->getEntityManager()->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceType')->findOneBy(array('type' => $type));
        $name = $this->findRsrcServ($resourceType);
        $form = $this->get($name)->getForm();
        $form->bindRequest($request);
 
        if($form->isValid())
        {   
            $user = $this->get('security.context')->getToken()->getUser();
            $this->get($name)->add($form, $id, $user);
            
            //return new RedirectResponse('claro_resource_index'); 
            return new Response("done");
        }
        else
        {
            return $this->render(
                'ClarolineCoreBundle:Resource:form_page.html.twig', array ('form' => $form->createView(), 'type' => $type, 'id' => $id)
            );
        }
    }
    
    public function defaultClickAction($type, $id)
    {
        $resourceType = $this->getDoctrine()->getEntityManager()->getRepository(
                'Claroline\CoreBundle\Entity\Resource\ResourceType')->findOneBy(array('type' => $type));
        $name = $this->findRsrcServ($resourceType);
        $response = $this->get($name)->getDefaultAction($id);
        
        return $response;
    }
    
    public function deleteAction($id)
    {
       $resource = $this->get('claroline.resource.manager')->find($id);
       $resourceType = $resource->getResourceType();
       $name = $this->findRsrcServ($resourceType);
       $this->get($name)->delete($resource);
       
       return new Response("delete");
    }
    
    public function openAction($id)
    {
       $resource = $this->get('claroline.resource.manager')->find($id);
       $resourceType = $resource->getResourceType();
       $name = $this->findRsrcServ($resourceType);
       $response = $this->get($name)->indexAction($resource);
       
       return $response;
    }
    
    public function getJSONResourceNodeAction($id)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch($method){
            case 'PUT':
                return $this->PUTNode($this->getRequest()->getContent());
                break;
            default:
                return $this->GETNode($id);     
                break;
        }  
        
        throw new \Exception("ResourceController getJSONResourceNode didn't work");
    }
    
    public function addToWorkspaceAction($idResource, $idWorkspace)
    {
        $resource = $this->get('claroline.resource.manager')->find($idResource);
        $em = $this->getDoctrine()->getEntityManager();
        $workspace = $em->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->find($idWorkspace);        
        $workspace->addResource($resource);
        $em->flush();
        
        return new Response("success");
    }
        
    public function removeFromWorkspaceAction($idResource, $idWorkspace)
    {
        $resource = $this->get('claroline.resource.manager')->find($idResource);
        $em = $this->getDoctrine()->getEntityManager();
        $workspace = $em->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->find($idWorkspace);     
        $workspace->removeResource($resource);
        $em->flush();
        
        return new Response ("success");
        
    }
    
    private function searchNewChild($JSONChildren, $rootChildren)
    {
        $tmp = 0;
        
        foreach ($JSONChildren as $JSONchild)
        {
            foreach($rootChildren as $rootChild)
            {
                if($JSONchild->id == $rootChild->getId())
                {
                    $tmp=1;
                }                       
            } 
                       
            if($tmp==0)
            {
                return $JSONchild;
            }
            $tmp=0;
        }
        
       return null;
    }
    
    private function PUTNode($put_str)
    {
        $user = $this->get('security.context')->getToken()->getUser(); 
        
        if($put_str != null)
        {
            $object = json_decode($put_str);
            
            if($object->id != 0)
            {
                $root = $this->get('claroline.resource.manager')->find($object->id);                
                $newChild = $this->searchNewChild($object->children, $root->getChildren());         
                
                if($newChild != null)
                {    
                    $newChild = $this->get('claroline.resource.manager')->find($newChild->id);
                    $newChild->setParent($this->get('claroline.resource.manager')->find($object->id));
                    $this->getDoctrine()->getEntityManager()->flush();  
                    $root = $this->get('claroline.resource.manager')->find($object->id);
                }
            }
            else
            {
                if(count($object->children) > count($this->get('claroline.resource.manager')->getRootResourcesOfUser($user)) )
                {
                    $newChild = $this->searchNewChild($object->children, $this->get('claroline.resource.manager')->getRootResourcesOfUser($user));
                    $resource = $this->get('claroline.resource.manager')->find($newChild->id);
                    $resource->setParent(null);
                    $this->getDoctrine()->getEntityManager()->flush(); 
                    $resources = $this->get('claroline.resource.manager')->getRootResourcesOfUser($user);
                    $root = new Directory();
                    $root->setName('root');
                    $root->setId(0);
                    $em = $this->getDoctrine()->getEntityManager();
                    $directoryType = $em->getRepository('ClarolineCoreBundle:Resource\ResourceType')->findBy(array('type' => 'directory'));
                    $root->setResourceType($directoryType[0]);
                    foreach($resources as $resource)
                    {
                        $root->addChildren($resource);
                    }           
                }
            }
            
            $content = $this->renderView( 'ClarolineCoreBundle:Resource:resource.json.twig', array('root' => $root));
            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/json'); 
            //todo change this response because it's really not that usefull
            return $response;   
        }
    }
    
    private function GETNode($id)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        
        if($id==0)
        {
            $resources = $this->get('claroline.resource.manager')->getRootResourcesOfUser($user);
            $root = new Directory();
            $root->setName('root');
            $root->setId(0);
            $em = $this->getDoctrine()->getEntityManager();
            $directoryType = $em->getRepository('ClarolineCoreBundle:Resource\ResourceType')->findBy(array('type' => 'directory'));
            $root->setResourceType($directoryType[0]);
            foreach($resources as $resource)
            {
                $root->addChildren($resource);
            }
        }
        else
        {
            $root = $this->get('claroline.resource.manager')->find($id);
        }
           
        $content = $this->renderView( 'ClarolineCoreBundle:Resource:resource.json.twig', array('root' => $root));
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/json'); 
        
        return $response;
    }
    
    private function findRsrcServ($resourceType)
    {
        $services = $this->container->getParameter("resource.service.list");
        $serviceName = null;
        
        foreach($services as $name => $service)
        {
            $type = $this->get($name)->getResourceType();
            
            if($type == $resourceType->getType())
            {
                $serviceName = $name;
            }
        }
        
        return $serviceName;
    }
}