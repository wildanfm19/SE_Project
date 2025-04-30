package com.ecommerce.project.service;

import com.ecommerce.project.exceptions.APIException;
import com.ecommerce.project.exceptions.ResourceNotFoundException;
import com.ecommerce.project.model.Category;
import com.ecommerce.project.model.Product;
import com.ecommerce.project.payload.ProductDTO;
import com.ecommerce.project.payload.ProductResponse;
import com.ecommerce.project.repositories.CategoryRepository;
import com.ecommerce.project.repositories.ProductRepository;
import org.modelmapper.ModelMapper;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Pageable;
import org.springframework.data.domain.Sort;
import org.springframework.stereotype.Service;
import org.springframework.web.multipart.MultipartFile;

import java.io.IOException;
import java.util.List;

@Service
public class ProductServiceImpl implements ProductService {

    @Autowired
    private ProductRepository productRepository;

    @Autowired
    private CategoryRepository categoryRepository;

    @Autowired
    private ModelMapper modelMapper;

    @Autowired
    private FileService fileService;

    @Value("${product.image}")
    private String path;


    @Override
    public ProductDTO addProduct(Long categoryId, ProductDTO productDTO) {
        Category category = categoryRepository.findById(categoryId)
                .orElseThrow(()-> new ResourceNotFoundException("Category","categoryId",categoryId));

        boolean isProductNotPresent = true;

        List<Product> products = category.getProducts();
        for (Product value : products) {
            if (value.getProductName().equals(productDTO.getProductName())) {
                isProductNotPresent = false;
                break;
            }
        }

        System.out.println("BEFORE MAPPING : " + productDTO.getDescription());

        if(isProductNotPresent){
            Product product = modelMapper.map(productDTO, Product.class);
            product.setCategory(category);
            product.setImage("default.png");
            System.out.println("AFTER Mapping Product Description : " + product.getDescription());

            double specialPrice = product.getPrice() - (product.getDiscount()* 0.01)  *  product.getPrice();

            product.setSpecialPrice(specialPrice);

            Product saveProduct = productRepository.save(product);

            return modelMapper.map(saveProduct, ProductDTO.class);
        } else{
            throw new APIException("Product already exist!!");
        }

    }

    @Override
    public ProductResponse searchByCategory(Long categoryId, Integer pageNumber, Integer pageSize, String sortBy, String sortOrder) {
        Category category = categoryRepository.findById(categoryId)
                .orElseThrow(()-> new ResourceNotFoundException("Category" , "categoryId" , categoryId));

        Sort sortByAndOrder = sortOrder.equalsIgnoreCase("asc")
                ? Sort.by(sortBy).ascending()
                : Sort.by(sortBy).descending();

        Pageable pageDetails = PageRequest.of(pageNumber, pageSize, sortByAndOrder);
        Page<Product> pageProducts = productRepository.findByCategoryOrderByPriceAsc(category , pageDetails);

        List<Product> products = pageProducts.getContent();

        List<ProductDTO> productDTOS = products.stream()
                .map(product -> modelMapper.map(product , ProductDTO.class))
                .toList();


        if(products.isEmpty()){
            throw new APIException(category.getCategoryName() + "category does not have any product");
        }
        ProductResponse productResponse = new ProductResponse();
        productResponse.setContent(productDTOS);
        productResponse.setPageNumber(pageProducts.getNumber());
        productResponse.setPageSize(pageProducts.getSize());
        productResponse.setTotalElements(pageProducts.getTotalElements());
        productResponse.setTotalPages(pageProducts.getTotalPages());
        productResponse.setLastPage(pageProducts.isLast());
        return productResponse;
    }



    @Override
    public ProductResponse getAllProducts(Integer pageNumber, Integer pageSize, String sortBy, String sortOrder) {
        Sort sortByAndOrder = sortOrder.equalsIgnoreCase("asc")
                ? Sort.by(sortBy).ascending()
                : Sort.by(sortBy).descending();
        Pageable pageDetails = PageRequest.of(pageNumber, pageSize, sortByAndOrder);
        Page<Product> productPage = productRepository.findAll(pageDetails);

      List<Product> products = productPage.getContent();
      List<ProductDTO> productDTOS = products.stream()
              .map(product -> modelMapper.map(product , ProductDTO.class))
              .toList();

      if(products.isEmpty()){
          throw new APIException("No Products Exist!!");
      }

      ProductResponse productResponse = new ProductResponse();
      productResponse.setContent(productDTOS);
        productResponse.setPageNumber(productPage.getNumber());
        productResponse.setPageSize(productPage.getSize());
        productResponse.setTotalElements(productPage.getTotalElements());
        productResponse.setTotalPages(productPage.getTotalPages());
        productResponse.setLastPage(productPage.isLast());
      return productResponse;
    }


    @Override
    public ProductResponse searchProductByKeyword(String keyword , Integer pageNumber, Integer pageSize, String sortBy, String sortOrder) {
        Sort sortByAndOrder = sortOrder.equalsIgnoreCase("asc")
                ? Sort.by(sortBy).ascending()
                : Sort.by(sortBy).descending();
        Pageable pageDetails = PageRequest.of(pageNumber, pageSize, sortByAndOrder);
        Page<Product> pageProducts = productRepository.findByProductNameLikeIgnoreCase('%' + keyword + '%',pageDetails);


        List<Product> products = pageProducts.getContent();
        List<ProductDTO> productDTOS = products.stream()
                .map(product -> modelMapper.map(product , ProductDTO.class))
                .toList();

        if(products.size() == 0){
            throw new APIException("Products not found with keyword: " + keyword);
        }
        ProductResponse productResponse = new ProductResponse();
        productResponse.setContent(productDTOS);
        productResponse.setPageNumber(pageProducts.getNumber());
        productResponse.setPageSize(pageProducts.getSize());
        productResponse.setTotalElements(pageProducts.getTotalElements());
        productResponse.setTotalPages(pageProducts.getTotalPages());
        productResponse.setLastPage(pageProducts.isLast());
        return productResponse;
    }

    @Override
    public ProductDTO updateProduct(Long productId, ProductDTO productDTO) {
        Product productDB = productRepository.findById(productId)
                .orElseThrow(() -> new ResourceNotFoundException("Product", "productId" , productId));

        Product product = modelMapper.map(productDTO , Product.class);

        productDB.setProductName(product.getProductName());
        productDB.setDescription(product.getDescription());
        productDB.setDescription(product.getDescription());
        productDB.setQuantity(product.getQuantity());
        productDB.setSpecialPrice(product.getSpecialPrice());

        productRepository.save(productDB);

        return modelMapper.map(productDB, ProductDTO.class);
    }

    @Override
    public ProductDTO deleteProduct(Long productId) {
        Product productDB = productRepository.findById(productId)
                .orElseThrow(() -> new ResourceNotFoundException("Product","productId",productId));

        productRepository.delete(productDB);

        return modelMapper.map(productDB, ProductDTO.class);
    }

    @Override
    public ProductDTO updateProductImage(Long productId, MultipartFile image) throws IOException {
        // Get product from the database
        Product productFromDB = productRepository.findById(productId)
                .orElseThrow(() -> new ResourceNotFoundException("Product","productId",productId));

        // Upload image to server
        // Get the file name of uploaded image
        String fileName = fileService.uploadImage(path,image);

        // Updating the new file names to the product
        productFromDB.setImage(fileName);

        // Save updated Product
        Product updatedProduct = productRepository.save(productFromDB);

        // return DTO after mapping product to DTO
        return modelMapper.map(updatedProduct, ProductDTO.class);
    }




}
