<?php
/**
 * User: stefano siciliano <s.siciliano@teknology.ch>
 * Date: 31/08/19
 * Time: 15:08
 */

namespace App\Controller;


use App\Entity\Product;
use App\Entity\ProductTags;
use App\Entity\Tags;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Zend\Code\Generator\DocBlock\Tag;

class ProductController extends AbstractController
{

    /**
     * @Route("/", name="index")
     */
    public function index()
    {
        return $this->redirectToRoute('product_list', [], 301);
    }
    /**
     * List of created products. It can be filtered by name, with name as query string parameter
     * @Route("/product/list/{tag}", name="product_list", methods={"GET"}, requirements={"tag"="\w+"})
     */
    public function list(Request $request, string $tag = "")
    {
        $tag_search = $request->get('tag_search') ?? '';
        $products = [];
        if($tag_search == ''){ 
            $products = $this->getDoctrine()->getRepository(Product::class)->findAll();
        }else{
            $tag = $this->getDoctrine()->getRepository(Tags::class)->findOneBy(['name' => addslashes(trim($tag_search))]);
            if($tag){
                $products_id = $this->getDoctrine()->getRepository(ProductTags::class)->findBy(['tag_id' => $tag->getId()]);
                if($products_id){
                    $pids = [];
                    foreach($products_id as $pid){
                        $pids[] = $pid->getProductId();
                    }
                    $products = $em = $this->getDoctrine()->getRepository(Product::class)->findById($pids);
                }
            }
        }

        return $this->render('product/list.html.twig', ['products' => $products, 'tag_search' => $tag_search]);
    }

    /**
     * Page for create a product
     * @Route("/product/create", name="product_create", methods={"GET", "POST"})
     */
    public function create_page(Request $request)
    {
        if($request->isMethod('post')){
            $em = $this->getDoctrine()->getManager();
            $product = new Product();
            $product->setName(addslashes($request->get('product_name')));
            $product->setDescription(addslashes($request->get('product_description')));
            $em->persist($product);
            $em->flush();

            $tags = explode(',', $request->get('tags'));
            foreach($tags as $tag){
                $tagExists = $this->getDoctrine()->getRepository(Tags::class)->findOneBy(['name' => trim($tag)]);
                if(!$tagExists){
                    $t = new Tags();
                    $t->setName(trim($tag));
                    $em->persist($t);
                    $em->flush();
                    $tid = $t->getId();
                }else{
                    $tid = $tagExists->getId();
                }

                $pt = new ProductTags();
                $pt->setProductId($product->getId());
                $pt->setTagId($tid);
                $em->persist($pt);
                $em->flush();
            }
            return $this->redirectToRoute('product_list', [], 302);
        }
        return $this->render('product/create.html.twig');
    }

    /**
     * @Route("/product/{pid}/edit", name="product_edit", methods={"GET", "POST"}, requirements={"pid"="\d+"})
     * @param int $pid id of the product to edit
     */
    public function edit_page(Request $request, int $pid)
    {
        try{
            $product = $this->getDoctrine()->getRepository(Product::class)->find($pid);
            $pt = $this->getDoctrine()->getRepository(ProductTags::class)->findBy(['product_id' => $pid]);
            $tags = [];
            foreach($pt as $p){
                $tag = $this->getDoctrine()->getRepository(Tags::class)->find($p->getTagId());
                if($tag){
                    $tags[] = $tag->getName();
                }
            }

            $data['tags'] = implode(',', $tags);
            $saved = null;
            if($request->isMethod('post')){
                $product->setName(addslashes($request->get('product_name')));
                $product->setDescription(addslashes($request->get('product_description')));
                $product->setUpdatedAt(date('Y-m-d H:i:s'));
                $em = $this->getDoctrine()->getManager();
                $em->persist($product);
                $em->flush();

                $tags = explode(',', $request->get('tags'));
                foreach($tags as $tag){
                    $tagExists = $this->getDoctrine()->getRepository(Tags::class)->findOneBy(['name' => trim($tag)]);
                    if(!$tagExists){
                        $t = new Tags();
                        $t->setName(trim($tag));
                        $em->persist($t);
                        $em->flush();
                        $tid = $t->getId();
                    }else{
                        $tid = $tagExists->getId();
                    }

                    $ptExist = $this->getDoctrine()->getRepository(ProductTags::class)->findOneBy(['product_id' => $product->getId(), 'tag_id' => $tid]);
                    if(!$ptExist){
                        $pt = new ProductTags();
                        $pt->setProductId($product->getId());
                        $pt->setTagId($tid);
                        $em->persist($pt);
                        $em->flush();
                    }
                }

                $saved = true;
                return $this->redirectToRoute('product_list', [], 301);
            }
            $data['product'] = $product;
            if(isset($saved)){
                $data['saved'] = $saved;
            }
            return $this->render('product/edit.html.twig', $data);
        }catch(\Exception $e){
            return $this->redirectToRoute('product_list', [], 301);
        }
    }
}