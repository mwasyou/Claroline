<?php

namespace Claroline\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Claroline\CoreBundle\Entity\Resource\Text;
use Claroline\CoreBundle\Entity\Resource\Revision;
use Claroline\CoreBundle\Form\TextType;

/**
 * TextManager will redirect to this controller once a directory is "open" or "edit".
 */
class TextController extends Controller
{

    /**
     * Edits a text.
     *
     * @param integer $textId
     *
     * @return Response
     */
    public function editAction($textId)
    {
        $request = $this->get('request');
        $user = $this->get('security.context')->getToken()->getUser();
        $text = $request->request->get('content');
        $em = $this->getDoctrine()->getEntityManager();
        $old = $em->getRepository('ClarolineCoreBundle:Resource\Text')->find($textId);
        $version = $old->getVersion();
        $revision = new Revision();
        $revision->setContent($text);
        $revision->setText($old);
        $revision->setVersion(++$version);
        $revision->setUser($user);
        $em->persist($revision);
        $old->setVersion($version);
        $old->setLastRevision($revision);

        $em->flush();

        return new Response('edited');
    }

    /**
     * Show the diff between every text verion. This function is a test.
     *
     * @param integer $textId
     *
     * @return type
     */
    public function historyAction($textId)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $text = $em->getRepository('ClarolineCoreBundle:Resource\Text')->find($textId);
        $revisions = $text->getRevisions();
        $size = count($revisions);
        $size--;
        $d = $i = 0;

        while ($i < $size) {
            $new = $revisions[$i]->getContent();
            $i++;
            $old = $revisions[$i]->getContent();

            //$doc = new \DOMDocument();
            //$doc->loadHTML($new);
            //$normalized = $doc->loadHTML($new);
            //var_dump($doc);

            $old = $this->get('claroline.text.manager')->tokenize($old);
            $new = $this->get('claroline.text.manager')->tokenize($new);

            $diff = $this->get('claroline.text.manager')->htmlDiff($old, $new);
            $differences[$d] = $this->get('claroline.text.manager')->untokenize($diff);

            $d++;
        }

        return $this->render('ClarolineCoreBundle:Text:history.html.twig', array('differences' => $differences,
                    'original' => $revisions[$size]->getContent())
        );
    }

}