package com.ecommerce.project.payload;

import java.util.ArrayList;
import java.util.List;

public class CartItemDTO {
    private Long cartItemId;
    private CartDTO cart;
    private ProductDTO productDTO;
    private Integer quantity;
    private Double discount;
    private Double productPrice;
}

