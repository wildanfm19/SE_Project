package com.ecommerce.project.service;

import com.ecommerce.project.payload.CartDTO;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;


public interface CartService {
    CartDTO addProductToCart(Long productId, Integer quantity);

    List<CartDTO> getAllCarts();

    CartDTO getCart(String emailId, Long cartId);

    
;

    @jakarta.transaction.Transactional
    CartDTO updateProductQuantityInCart(Long productId, Integer quantity);

    String deleteProductFromCart(Long cartId, Long productId);
}
