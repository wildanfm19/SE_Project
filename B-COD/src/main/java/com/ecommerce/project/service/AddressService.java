package com.ecommerce.project.service;

import com.ecommerce.project.model.User;
import com.ecommerce.project.payload.AddressDTO;
import org.springframework.data.jpa.repository.Query;

import java.util.List;

public interface AddressService {
    AddressDTO createAddress(AddressDTO addressDTO, User user);

    List<AddressDTO> getAddress();

    AddressDTO getAddressById(Long addressId);

    List<AddressDTO> getUserAddress(User user);

    AddressDTO updateAddressById(Long addressId, AddressDTO addressDTO);


    @Query("DELETE FROM Address a WHERE a.id = ?1")
    String deletedAddressById(Long addressId);
}
