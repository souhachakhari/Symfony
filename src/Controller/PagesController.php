<?php
namespace App\Controller;

use App\Entity\User;
use App\Entity\Note;
use App\Form\NoteType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class PagesController extends AbstractController
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/', name: 'home')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_connexion');
    }

    #[Route('/connexion', name: 'app_connexion')]
    public function connexion(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_pages');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('pages/connexion.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function inscri(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $error = null;

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if ($password !== $confirmPassword) {
                $error = "Les mots de passe ne correspondent pas.";
            } else {
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existingUser) {
                    $error = "Un compte existe déjà avec cet email.";
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                    $entityManager->persist($user);
                    $entityManager->flush();

                    return $this->redirectToRoute('app_connexion');
                }
            }
        }

        return $this->render('pages/inscription.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/inscription', name: 'app_inscription')]
    public function inscription(): Response
    {
        return $this->redirectToRoute('app_register');
    }

    #[Route('/pages', name: 'app_pages')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory
    ): Response {
        $user = $this->security->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_connexion');
        }

        $note = new Note();
        $note->setCreatedAt(new \DateTimeImmutable());
        $note->setUser($user);

        $form = $formFactory->createBuilder()
            ->add('content', TextareaType::class, [
                'label' => 'Votre note',
                'attr' => ['placeholder' => 'Écrivez quelque chose...']
            ])
            ->add('save', SubmitType::class, ['label' => 'Ajouter'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note->setContent($form->get('content')->getData());
            $entityManager->persist($note);
            $entityManager->flush();

            return $this->redirectToRoute('app_pages');
        }

        $notes = $entityManager->getRepository(Note::class)->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('pages/index.html.twig', [
            'form' => $form->createView(),
            'notes' => $notes
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig');
    }

    #[Route('/note/edit/{id}', name: 'note_edit')]
    public function edit($id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_connexion');
        }
    
        $note = $entityManager->getRepository(Note::class)->find($id);
        if (!$note || $note->getUser() !== $user) {
            throw $this->createNotFoundException('Note non trouvée ou accès refusé');
        }
    
        $form = $this->createForm(NoteType::class, $note);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_pages');
        }
    
        return $this->render('pages/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

   #[Route('/note/delete/{id}', name: 'note_delete', methods: ['POST'])]
public function delete($id, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrfTokenManager): Response
{
    $user = $this->security->getUser();
    if (!$user) {
        return $this->redirectToRoute('app_connexion');
    }

    $note = $entityManager->getRepository(Note::class)->find($id);
    if (!$note || $note->getUser() !== $user) {
        throw $this->createNotFoundException('Note not found or not authorized');
    }

    $submittedToken = $request->request->get('_token');
    if (!$csrfTokenManager->isTokenValid(new CsrfToken('delete' . $note->getId(), $submittedToken))) {
        throw $this->createAccessDeniedException('Jeton CSRF invalide');
    }

    $entityManager->remove($note);
    $entityManager->flush();

    return $this->redirectToRoute('app_pages');
}

}
