<?php

namespace App\Controller;


use App\Entity\Badge;
use App\Entity\FriendshipRelation;
use App\Entity\Grade;
use App\Entity\Hangman;
use App\Entity\Memory;
use App\Entity\Message;
use App\Entity\Quote;
use App\Entity\Result;
use App\Entity\Session;
use App\Entity\Sessioncomment;
use App\Entity\Student;
use App\Entity\Subject;
use App\Entity\Subjectlevel;
use App\Entity\Training;
use App\Form\ProfileFormType;
use App\Form\SubjetLevelFormType;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class StudentController
 *
 * @IsGranted("ROLE_USER")
 */
class StudentController extends Controller
{
    /**
     * @Route("/dashboard", name="default_student_connected")
     * @throws \Exception
     */

    public function homeStudentAction()
    {
        $tpm = array();
        $session = $this->getDoctrine()->getRepository(Session::class)->findall();
        $nbSessionOrganized = $this->getDoctrine()->getRepository(Session::class)->countNbSessionOrganizedByUser(
            $this->getUser()
        );



            //quotes generator
        $quoteList = $this->getDoctrine()->getRepository(Quote::class)->findAll();
        $quoteId = array_rand($quoteList);
        $quote = $quoteList[$quoteId];
        //end quote generator


        /*calcul du nombre de sessions passées où l'étudiant a été inscrit*/
        $now = new DateTime("now");
        $now->format('Y-m-d');

        $hourNow = new DateTime('now', new \DateTimeZone('Europe/Paris'));
        $hourNow->format('H:i:s');

        $listSessionAttended = $this->getUser()->getSessionid();
        $nbSessionAttended = 0;
        $listSessionDone = new ArrayCollection();

        foreach ($listSessionAttended as $sessionAttended) {
            //on recupère la liste des commentaires sur la session parcourue
            $listcommentaires = $this->getDoctrine()->getRepository(Sessioncomment::class)->findBy(array('idSession' => $sessionAttended->getId(), 'idStudent' => $this->getUser()->getId()));

            if ($sessionAttended->getDate() < $now && $sessionAttended->getEndingtime() < $hourNow) {

                //la session est passée, on incrémente le compteur de séances assistées
                $nbSessionAttended++;
                //s'il n'y a pas de commentaires de l'utilisateur connecté sur la session, on l'ajoute en affichage des sessions a commenter
                if (empty($listcommentaires)) {
                    $listSessionDone->add($sessionAttended);
                }

            }

        }

        foreach ($session as $key => $value) {
            $now = new \DateTime();
            if ($value->getDate() > $now && sizeOf($tpm) < 3) {
                array_push($tpm, $value);
            }
        }

        $student = $this->getUser();
        /** @var Session $listeSession */
        $listeSession = $student->getSessionid();

        $tmp = array();
        $listeSessionEtudiant = array();

        // On parcourt les séances auxquelles l'utilisateur est déjà inscrit
        foreach ($listeSession as $sessionTMP) {
            // On ajoute les séances
            array_push($tmp, $sessionTMP->getId());
        }

        foreach ($tmp as $ss) {
            // On ajoute les ID des sessions
            array_push($listeSessionEtudiant, $ss);
        }

        $messages = $this->getDoctrine()->getRepository(Message::class)->findByStudent($this->getUser()->getId());
        $messagesTmp = array();
        $arrayIdSender = array();
        // On parcourt les messages
        foreach ($messages as $m) {
            // Si l'ID de la personne qui a envoyé le message n'est pas dans le tableau
            if (!(in_array($m->getIdSender()->getId(), $arrayIdSender))) {
                array_push($arrayIdSender, $m->getIdSender()->getId());
                array_push($messagesTmp, $m);
            }
        }

        return $this->render("dashboard.html.twig", [
            'session' => $tpm,
            'sessionUser' => $listeSessionEtudiant,
            'nbSessionOrganized' => $nbSessionOrganized,
            'nbSessionAttended' => $nbSessionAttended,
            "messages" => $messagesTmp,
            "quote" => $quote,
            "doneSessions" => $listSessionDone
        ]);
    }

    /**
     * @Route("/search", name="search_student_view")
     */

    public function searchStudent(Request $request)
    {
        if ($request->get('search_student')) {
            $result_student = $this->getDoctrine()->getRepository(Student::class)->searchStudent(
                $request->get('search_student')
            );

            return $this->render(
                'friend.search.html.twig',
                [
                    'students' => $result_student
                ]
            );
        } else {
            return $this->redirectToRoute('default_student_connected');
        }
    }

    /**
     * @return string
     */
    private function generateUniqueFileName()
    {
        // md5() reduces the similarity of the file names generated by
        // uniqid(), which is based on timestamps
        return md5(uniqid());
    }

    /**
     * @Route("/profile/", name="student_profile")
     */

    public function studentProfileAction(Request $request, ObjectManager $manager)
    {
        $user = $this->getUser();
        $noteUser = $this->getDoctrine()->getRepository(Subjectlevel::class)->findBy(
            [
                "studentid" => $this->getUser(),

            ]);
        $subjectlevel = new Subjectlevel();
        $em = $this->getDoctrine()->getManager();

        $subjectList = $this->get('subject_service')->getSubjectListForSubjectLevelingForUser($user);
        $form = $this->createForm(SubjetLevelFormType::class, $subjectlevel, [
            'subject' => $subjectList
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $subjectlevel->setStudentid($this->getUser());
            $em->persist($subjectlevel);
            $em->flush();

            return $this->redirectToRoute("student_profile");
        }

        $subjectLevelStudent = $this->getDoctrine()->getRepository(Subjectlevel::class)->findBy([
            'studentid' => $this->getUser()
        ]);

        $avatar = $this->getUser()->getavatar();

        $oldFileName = $this->getUser()->getAvatar();
        if ($request->getMethod() == 'POST') {
            if (!is_null($request->request->get('editer'))) {
                $repository = $this->getDoctrine()->getRepository(Student::class);

                /* @var Training $formations */

                $user = $this->getUser();
                $studentId = $repository->find($this->getUser()->getId());

                $form = $this->createForm(ProfileFormType::class, $user);

                $form->handleRequest($request);
                if ($form->isSubmitted() && $form->isValid()) {
                    $file = $studentId->getAvatar();
                    if ($file != null) {
                        unlink($this->getParameter('avatar_directory') . '/' . $oldFileName);
                        $fileName = $this->generateUniqueFileName() . '.' . $file->guessExtension();

                        try {
                            $file->move(
                                $this->getParameter('avatar_directory'),
                                $fileName
                            );
                        } catch (FileException $e) {
                            // TODO Gérer les erreurs
                        }
                        $user->setAvatar($fileName);


                    } else {

                        $studentId->setavatar($avatar);
                    }


                    $manager->persist($user);
                    $manager->flush();

                    return $this->redirectToRoute("student_profile");

                }

                return $this->render("updateProfile.html.twig", ["formUser" => $form->createView(), "user" => $this->getUser()]);

            } else if (!is_null($request->request->get('matieres'))) {
                $repository = $this->getDoctrine()->getRepository(Subject::class);
                $subject = new Subject();
                $form = $this->createFormBuilder($subject)
                    ->add('name')
                    ->getForm();

                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    if ($repository->findBy(
                        ['name' => $form->get('name')->getData()]
                    )) {

                    } else {
                        $manager->persist($subject);
                        $manager->flush();

                        return $this->redirectToRoute("student_profile");
                    }
                }

                return $this->render("informASubject.html.twig", ["formSubject" => $form->createView()]);
            }

        }

        return $this->render(
            "viewProfile.html.twig",
            [
                'form' => $form->createView(),
                'subjectlevel' => $subjectLevelStudent,
                'noteUser' => $noteUser,
                'subjectList' => $subjectList
            ]
        );
    }

    /**
     * @Route("/delete-subject-level/{id}", name="delete_subject_level")
     */
    public function deleteSubjectLevel(Request $request, Subjectlevel $subjectlevel)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($subjectlevel);
        $em->flush();

        return $this->redirectToRoute('student_profile');
    }

    /**
     * @Route("/profil/{uuid_student}", name="student_other_profile")
     */
    public function studentOtherProfileAction($uuid_student)
    {
        $student = $this->getDoctrine()->getRepository(Student::class)->findOneBy(
            [
                'uuid' => $uuid_student,
            ]
        );
        $user = $this->getUser();
        if (!$student) {
            return $this->redirectToRoute("default_student_connected");
        } elseif ($uuid_student == $this->getUser()->getUuid()) {
            return $this->redirectToRoute('student_profile');
        }
        $waiting_for_accept = false;
        $is_friend = false;
        $are_not_unknow = false;
        $are_not_unknow = $this->getDoctrine()->getRepository(FriendshipRelation::class)->checkIfAreUnknow(
            $user,
            $student
        );

        if ($are_not_unknow) {
            $waiting_for_accept = $this->getDoctrine()->getRepository(FriendshipRelation::class)->checkIfNotAccepted(
                $user,
                $student
            );

            $is_friend = $this->getDoctrine()->getRepository(FriendshipRelation::class)->checkIfAreFriend(
                $user,
                $student
            );
        }
        $noteUser = $this->getDoctrine()->getRepository(Subjectlevel::class)->findBy(
            [
                "studentid" => $student,

            ]);

        return $this->render(
            'viewOtherProfile.html.twig',
            [
                'noteUser' => $noteUser,
                'student' => $student,
                'are_not_unknow' => $are_not_unknow,
                'waiting_for_accept' => $waiting_for_accept,
                'is_friend' => $is_friend
            ]
        );
    }

    /**
     * @Route("/createSubject", name="createSubject")
     */
    public function createSubject(Request $request, ObjectManager $manager)
    {

    }

    /**
     * @Route("/informASubject", name="informASubject")
     */
    public function informASubject(Request $request, ObjectManager $manager)
    {


    }

    public function sessionAction()
    {

    }

    /**
     * @Route("/classementxp", name="classementXp")
     */
    public function classementXp()
    {
        $classement = $this->getDoctrine()->getEntityManager();
        $tags = $classement->getRepository(Student::class)->findBy(
            array(), array('xpwon' => 'DESC')
        );

        return $this->render(
            'classementxp.html.twig',
            [
                'classementxp' => $tags
            ]
        );

    }


    /**
     * @param $idJeu
     * @Route("/tableauScore/{idJeu}", name="tableauscore")
     */
    public function voirTableauScore($idJeu)
    {
        $memoryRepo = $this->getDoctrine()->getRepository(Memory::class);
        $hangmanRepo = $this->getDoctrine()->getRepository(Hangman::class);

        if ($idJeu == 0) {
            $tags = $hangmanRepo->findBestScoreByStudent();
        } else {
            $tags = $memoryRepo->findBestScoreByStudent();
        }
        return $this->render(
            'tableauScore.html.twig',
            [
                'classementMemory' => $tags
            ]
        );

    }

    /**
     * @Route("/testbadge", name="testbadge")
     */
    public function addBadge()
    {
        $badge = $this->getDoctrine()->getRepository(Badge::class)->find(3);
        $this->get('ajout_badge')->addBadge($this->getUser(), $badge);
        return new JsonResponse();
    }

    /**
     * @Route("/testgrade", name="testgrade")
     */
    public function addGrade()
    {
        $grade = $this->getDoctrine()->getRepository(Grade::class)->find(2);
        $this->get('ajout_grade')->addGrade($this->getUser(), $grade);
        return new JsonResponse();
    }

    /**
     * @Route("/testxp", name="testxp")
     */
    public function xpWon()
    {
        $this->get('xp_won')->wonXp($this->getUser(), 100);
        return new JsonResponse();
    }

    /**
     * @Route("/searchClassement", name="search_xp_view")
     */
    public function searchStudentInClassementXp(Request $request)
    {
        if ($request->get('search_xp')) {
            $result_xp = $this->getDoctrine()->getRepository(Student::class)->searchStudent(
                $request->get('search_xp')
            );
            return $this->render(
                'classementxp.html.twig',
                [
                    'classementxpSearch' => $result_xp,
                    'classementxp' => null,
                ]
            );
        } else {
            return $this->redirectToRoute('default_student_connected');
        }
    }


    /**
     * @Route("/searchScore", name="search_score_view")
     */
    public function searchStudentInTableauScore(Request $request)
    {
        if ($request->get('search_score')) {
            $result_score = $this->getDoctrine()->getRepository(Student::class)->searchStudent(
                $request->get('search_score')
            );
            $tab = [];
            foreach ($result_score as $result) {

                $tab = $this->getDoctrine()->getRepository(Result::class)->findBy(
                    ['id' => $result->getId(),]
                );
            }
            return $this->render(
                'tableauScore.html.twig',
                [
                    'score' => $result_score,
                    'result' => null,
                    'resulta' => $tab
                ]
            );
        } else {
            return $this->redirectToRoute('default_student_connected');
        }
    }
}