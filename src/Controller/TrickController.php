<?php

namespace App\Controller;

use DateTime; 
use App\Entity\User;
use App\Entity\Image;
use App\Entity\Trick;
use App\Entity\Video;
use App\Entity\Comment;
use App\Form\AppFormFactory;
use App\Repository\CommentRepository;
use App\Services\TrickServiceInterface; 
use App\Services\CommentServiceInterface;
use App\Services\VideoServiceInterface;
use App\Services\ImageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


class TrickController extends AbstractController
{   
    private $trickService;  
    private $formFactory;

    public function __construct(TrickServiceInterface $trickService, AppFormFactory $formFactory) {
        $this->trickService = $trickService; 
        $this->formFactory = $formFactory; 
    }

     /**
     * @Route("show/trick/{id}", 
     *     name="trick_show", 
     *     methods={"HEAD", "GET", "POST"}), 
     *     @Entity("trick", expr="repository.findOneById(id)")
     */
    public function show(Trick $trick, Request $request, CommentServiceInterface $commentService, CommentRepository $commentRepo): Response
    {
        //creation of the form
        $comment = New Comment(); 
        $commentForm = $this->formFactory->create('trick-comment', $comment); 
        
        $limit = 5; 

        // We get the page number
        $page = (int)$request->query->get("page", 1);

        // We get the comments of the page
        $paginatedComments = $commentRepo->getPaginatedComments($page, $limit, $trick); 

        // We get the total number of comments
        $totalComments = $commentRepo->getTotalComments($trick); 

        //handling of the form
        $commentForm->handleRequest($request); 
        if($commentForm->isSubmitted() && $commentForm->isValid()) {

            //We assign the user id to the comment author
            $comment->setUser($this->getUser());

            //We add the comment content and the trick to the trick comment
            $commentService->add($comment, $trick);
        }
        
        return $this->render('trick/show.html.twig', [
            'trick' => $trick, 
            'paginatedComments' => $paginatedComments, 
            'commentForm' => $commentForm->createView(), 
            'totalComments' => $totalComments, 
            'limit' => $limit, 
            'page' => $page
        ]);
    }

    /**
     * @Route("/create/trick", 
     *     name="trick_create", 
     *     methods={"HEAD", "GET", "POST"})
     */
    public function create(Request $request)
     {   
        $trick = New Trick(); 
        $form = $this->formFactory->create('trick-create', $trick); 
        $form->handleRequest($request); 

        //if the form is submitted, we hydrate the trick and send it to the DB
        if($form->isSubmitted() && $form->isValid()) {
                $this->trickService->add($trick, $form); 
            
                return $this->redirectToRoute('trick_show', [
                    'id' => $trick->getId()
            ]); 
        } 
     
        return $this->render('trick/creation.html.twig', [
             'formTrickCreation' => $form->createView()
         ]); 
     }

    /**
    * @Route("/update/trick/{id}", 
    *     name="trick_update", 
    *     methods={"HEAD", "GET", "POST"})
    */
    public function update(Trick $trick, Request $request)
     {  
        $form = $this->formFactory->create('trick-update', $trick); 
        $form->handleRequest($request); 

        //if the form is submitted, we hydrate the trick and send it to the DB by using the service
        if($form->isSubmitted() && $form->isValid()) {                 
            $this->trickService->update($trick); 
            
            //We then return the updated trick
            return $this->redirectToRoute('trick_show', [
                'id' => $trick->getId()
            ]); 
        } 
        
        return $this->render('trick/update.html.twig', [
            'formTrickCreation' => $form->createView(), 
            'trick' => $trick, 
             'form' => $form->createView()
        ]); 
     }

    /**
    * @Route("/delete/trick/{id}", 
    *     name="trick_delete", 
    *     methods={"HEAD", "GET", "POST"})
    */
    public function delete(Trick $trick): RedirectResponse  
     {
        $this->trickService->remove($trick); 
        return $this->redirectToRoute('home'); 
     }

    /**
    * @Route("/delete/video/{id}", 
    *     name="trick_video_delete", 
    *     methods={"HEAD", "GET", "POST"})
    */
    public function deleteVideo(Video $video, VideoServiceInterface $videoService): RedirectResponse  
    {
        $videoService->remove($video); 
        return $this->redirectToRoute('trick_update', [
            'id' => $video->getTrick()->getId()
        ]); 
    }

    /**
    * @Route("/delete/image/{id}", 
    *     name="trick_image_delete", 
    *     methods={"HEAD", "GET", "POST"})
    */
    public function deleteImage(Image $image): RedirectResponse  
    {
        $imageService->remove($image); 
        return $this->redirectToRoute('trick_update', [
            'id' => $image->getTrick()->getId()
        ]); 
    }
    
    /**
     * @Route("/loadMoreTricks/{offset}/{quantity}", 
     *     name="load_more_tricks", 
     *     methods={"HEAD", "GET", "POST"}) 
     */
    public function loadMore(Request $request, $offset, $quantity = 4, EntityManagerInterface $em) 
    {
      //$tricks = $this->getDoctrine()->getRepository(Trick::class)->findNextTricks($offset, $quantity); 
      $tricks = $this->trickService->findNextTricks($offset, $quantity); 
      //$tricks = $this->get('trickrepository')->findNextTricks($offset, $quantity); 

        $arrayCollection = array();

        foreach($tricks as $trick) {
            $arrayCollection[] = array(
                'id' => $trick->getId(), 
                'name' => $trick->getName(),
                'description' => $trick->getDescription(), 
                'coverImagePath' => $trick->getCoverImagePath()
            );
        }

        return new JsonResponse($arrayCollection); 
    }
}