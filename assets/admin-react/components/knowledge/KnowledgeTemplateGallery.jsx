/**
 * KnowledgeTemplateGallery Component
 *
 * Pre-built knowledge templates that users can select and customize.
 * Similar to skill templates – provides ready-to-use knowledge content
 * for common business scenarios.
 */
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import PropTypes from 'prop-types';
import {
    ShieldCheck,
    Package,
    Building2,
    HelpCircle,
    Truck,
    CreditCard,
    Clock,
    Users,
    FileText,
    Globe,
    Headphones,
    BadgePercent,
    BookOpen,
    Scale,
    ShoppingBag,
    Megaphone,
} from 'lucide-react';

// ─── Pre-built Knowledge Templates ───────────────────────────────────────────
const KNOWLEDGE_TEMPLATES = [
    // ── Policies ──────────────────────────────────────────────────
    {
        id: 'return-refund-policy',
        name: 'Return & Refund Policy',
        category: 'policies',
        icon: 'ShieldCheck',
        description: 'Comprehensive return and refund policy covering timelines, eligibility, and process.',
        content: `# Return & Refund Policy

## Return Window
- Products may be returned within **30 days** of delivery
- Items must be in original condition with tags attached
- Sale items are final sale unless defective

## Refund Process
1. Customer initiates return request via account or support
2. Return shipping label is provided (prepaid for defective items)
3. Item is inspected upon receipt (1-3 business days)
4. Refund is processed to original payment method within 5-7 business days

## Non-Returnable Items
- Gift cards and downloadable products
- Perishable goods and personal care items
- Custom or personalized orders
- Items marked as "Final Sale"

## Exchanges
- Exchanges are available for different sizes/colors of the same product
- Subject to availability
- No additional shipping charges for exchanges due to our error

## Damaged or Defective Items
- Report within 48 hours of delivery with photos
- Full refund or replacement offered at customer's choice
- We cover return shipping for defective items`,
    },
    {
        id: 'shipping-policy',
        name: 'Shipping Policy',
        category: 'policies',
        icon: 'Truck',
        description: 'Shipping methods, timelines, tracking, and international delivery information.',
        content: `# Shipping Policy

## Domestic Shipping
| Method | Delivery Time | Cost |
|--------|--------------|------|
| Standard | 5-7 business days | Free on orders over $50 |
| Express | 2-3 business days | $9.99 |
| Overnight | Next business day | $24.99 |

## Order Processing
- Orders placed before 2:00 PM EST ship same day
- Orders placed after 2:00 PM EST ship next business day
- Weekend/holiday orders ship the next business day

## Tracking
- Tracking number emailed within 24 hours of shipment
- Real-time tracking available on our website and via carrier
- SMS updates available for express and overnight orders

## International Shipping
- Available to 50+ countries
- Delivery: 7-21 business days depending on destination
- Customs duties and taxes are the customer's responsibility
- We are not responsible for delays due to customs processing

## Lost or Delayed Packages
- Contact us if package hasn't arrived within estimated window + 5 days
- We will file a claim and provide a replacement or refund
- Insurance included on orders over $100`,
    },
    {
        id: 'privacy-policy',
        name: 'Privacy Policy',
        category: 'policies',
        icon: 'Scale',
        description: 'Data collection, usage, and privacy practices for customer information.',
        content: `# Privacy Policy Summary

## Data We Collect
- **Account Information**: Name, email, phone number, shipping address
- **Payment Information**: Processed securely via third-party payment processors (we don't store card numbers)
- **Usage Data**: Browsing behavior, purchase history, preferences
- **Communication**: Support tickets, chat history, feedback

## How We Use Data
- Process orders and manage accounts
- Personalize shopping experience and recommendations
- Send order updates and shipping notifications
- Improve our products and services
- Marketing communications (with opt-in consent only)

## Data Protection
- SSL encryption on all data transmissions
- PCI-DSS compliant payment processing
- Regular security audits and monitoring
- Data stored on secure, encrypted servers

## Customer Rights
- Access and download personal data at any time
- Request data deletion ("Right to be Forgotten")
- Opt-out of marketing communications
- Update or correct personal information via account settings

## Cookies
- Essential cookies for site functionality (always active)
- Analytics cookies to improve user experience (opt-in)
- Marketing cookies for personalized ads (opt-in)

## Contact
For privacy inquiries, contact our Data Protection Officer at privacy@company.com`,
    },
    {
        id: 'payment-policy',
        name: 'Payment Policy',
        category: 'policies',
        icon: 'CreditCard',
        description: 'Accepted payment methods, billing, and payment security information.',
        content: `# Payment Policy

## Accepted Payment Methods
- **Credit/Debit Cards**: Visa, Mastercard, American Express, Discover
- **Digital Wallets**: Apple Pay, Google Pay, PayPal
- **Buy Now Pay Later**: Klarna, Afterpay (on orders $50+)
- **Gift Cards**: Company gift cards accepted at checkout

## Billing
- Payment is charged at the time of order placement
- Pre-authorization may appear on your statement for pending orders
- Taxes calculated based on shipping destination

## Payment Security
- All transactions encrypted with 256-bit SSL
- PCI-DSS Level 1 compliant
- 3D Secure authentication supported
- Fraud detection and prevention measures in place

## Failed Payments
- If payment fails, order is held for 24 hours
- Customer notified via email to update payment method
- Order automatically cancelled after 24 hours if unresolved

## Subscriptions
- Recurring payments charged on the same date monthly
- Cancel anytime from account settings
- No cancellation fees
- Prorated refunds available for annual plans`,
    },

    // ── Products ──────────────────────────────────────────────────
    {
        id: 'product-catalog-overview',
        name: 'Product Catalog Overview',
        category: 'products',
        icon: 'ShoppingBag',
        description: 'Overview of product categories, features, and bestsellers.',
        content: `# Product Catalog Overview

## Product Categories
- **Clothing & Apparel**: Men's, Women's, Kids, Accessories
- **Electronics**: Smartphones, Laptops, Audio, Wearables
- **Home & Living**: Furniture, Decor, Kitchen, Bedding
- **Health & Beauty**: Skincare, Haircare, Supplements, Fitness

## Key Features
- All products come with a satisfaction guarantee
- Free shipping on orders over $50
- Easy 30-day returns on most items
- Product reviews and ratings from verified buyers

## Bestsellers
1. **Premium Wireless Earbuds** - $79.99 (4.8, 2,340 reviews)
2. **Organic Cotton T-Shirt** - $29.99 (4.7, 1,890 reviews)
3. **Smart Home Hub** - $129.99 (4.6, 987 reviews)
4. **Natural Face Serum** - $44.99 (4.9, 3,210 reviews)

## Product Quality
- Rigorous quality control on all products
- Sourced from trusted, verified suppliers
- Eco-friendly and sustainable options available
- Detailed product specifications and sizing guides provided

## Custom Products
- Personalization available on select items
- Corporate/bulk ordering with discounts
- Gift wrapping available at checkout ($4.99)`,
    },
    {
        id: 'product-sizing-guide',
        name: 'Product Sizing Guide',
        category: 'products',
        icon: 'Package',
        description: 'Size charts, measurement guides, and fit recommendations.',
        content: `# Sizing Guide

## How to Measure
1. **Chest**: Measure around the fullest part of your chest
2. **Waist**: Measure at your natural waistline
3. **Hips**: Measure around the fullest part of your hips
4. **Inseam**: From crotch to ankle bone

## Men's Sizes
| Size | Chest (in) | Waist (in) | Hips (in) |
|------|-----------|-----------|----------|
| S    | 35-37     | 29-31     | 35-37    |
| M    | 38-40     | 32-34     | 38-40    |
| L    | 41-43     | 35-37     | 41-43    |
| XL   | 44-46     | 38-40     | 44-46    |
| XXL  | 47-49     | 41-43     | 47-49    |

## Women's Sizes
| Size | Bust (in) | Waist (in) | Hips (in) |
|------|----------|-----------|----------|
| XS   | 31-32    | 24-25     | 34-35    |
| S    | 33-34    | 26-27     | 36-37    |
| M    | 35-36    | 28-29     | 38-39    |
| L    | 37-39    | 30-32     | 40-42    |
| XL   | 40-42    | 33-35     | 43-45    |

## Fit Tips
- Our clothing runs true to size
- If between sizes, we recommend sizing up for relaxed fit
- Check individual product pages for specific fit notes
- Free exchanges available for size issues`,
    },

    // ── Company Info ──────────────────────────────────────────────
    {
        id: 'company-about',
        name: 'About the Company',
        category: 'company_info',
        icon: 'Building2',
        description: 'Company background, mission, values, and team information.',
        content: `# About Our Company

## Our Story
Founded in 2018, we started with a simple mission: to provide high-quality products at fair prices with exceptional customer service. What began as a small online shop has grown into a trusted brand serving customers worldwide.

## Mission Statement
To make premium products accessible to everyone while maintaining the highest standards of quality, sustainability, and customer satisfaction.

## Our Values
- **Quality First**: Every product meets rigorous quality standards
- **Customer Obsessed**: Your satisfaction is our top priority
- **Sustainability**: Committed to eco-friendly practices and packaging
- **Transparency**: Honest pricing, clear policies, no hidden fees
- **Innovation**: Continuously improving products and experience

## Key Facts
- Serving 100,000+ customers across 50+ countries
- 4.8 average rating on Trustpilot
- 98% customer satisfaction rate
- Carbon-neutral shipping since 2023
- B Corp certified

## Contact Information
- **Email**: support@company.com
- **Phone**: 1-800-XXX-XXXX (Mon-Fri, 9AM-6PM EST)
- **Live Chat**: Available on website 24/7
- **Social Media**: @companyname on Instagram, Twitter, Facebook
- **Address**: 123 Business St, Suite 100, City, State 12345`,
    },
    {
        id: 'business-hours',
        name: 'Business Hours & Support',
        category: 'company_info',
        icon: 'Clock',
        description: 'Operating hours, support channels, and response time expectations.',
        content: `# Business Hours & Support

## Customer Support Hours
| Channel | Availability | Response Time |
|---------|-------------|---------------|
| Live Chat | 24/7 | Instant |
| Email | 24/7 (responses during business hours) | Within 4 hours |
| Phone | Mon-Fri 9AM-6PM EST | Immediate |
| Social Media | Mon-Fri 9AM-8PM EST | Within 2 hours |

## Holiday Schedule
- Major holidays: Closed (New Year's Day, Thanksgiving, Christmas)
- Holiday Eve: Reduced hours (9AM-2PM EST)
- Orders placed during holidays processed next business day

## Urgent Issues
For urgent matters outside business hours:
- Use live chat for instant AI-assisted support
- Email with "URGENT" in subject line for priority handling
- Emergency order issues: Call our after-hours line

## Escalation Process
1. **Level 1**: Chat/email support agent (initial response)
2. **Level 2**: Senior support specialist (complex issues)
3. **Level 3**: Manager escalation (unresolved cases)
- Target resolution: 90% of issues resolved at Level 1
- Average resolution time: 2.5 hours`,
    },
    {
        id: 'team-departments',
        name: 'Team & Departments',
        category: 'company_info',
        icon: 'Users',
        description: 'Internal team structure, departments, and contact points.',
        content: `# Team & Departments

## Customer Service
- **Role**: Handle inquiries, orders, returns, and complaints
- **Contact**: support@company.com
- **Specialties**: Order issues, product questions, account help
- **Language Support**: English, Spanish, French

## Sales Team
- **Role**: Bulk orders, corporate partnerships, wholesale inquiries
- **Contact**: sales@company.com
- **Specialties**: Custom quotes, volume pricing, B2B partnerships

## Technical Support
- **Role**: Website issues, payment problems, account security
- **Contact**: tech@company.com
- **Specialties**: Login issues, payment errors, data requests

## Marketing & Partnerships
- **Role**: Influencer collaborations, affiliate program, PR
- **Contact**: marketing@company.com
- **Specialties**: Brand partnerships, affiliate inquiries, press requests

## Shipping & Fulfillment
- **Role**: Order tracking, shipping issues, delivery problems
- **Contact**: shipping@company.com
- **Specialties**: Lost packages, address changes, special delivery requests`,
    },

    // ── General / FAQ ─────────────────────────────────────────────
    {
        id: 'faq-general',
        name: 'General FAQ',
        category: 'general',
        icon: 'HelpCircle',
        description: 'Frequently asked questions about ordering, accounts, and general inquiries.',
        content: `# Frequently Asked Questions

## Orders & Shopping

**Q: How do I place an order?**
A: Browse products, add to cart, proceed to checkout, enter shipping and payment info, and confirm your order.

**Q: Can I modify or cancel my order?**
A: Orders can be modified/cancelled within 1 hour of placement. After that, the order enters processing and cannot be changed.

**Q: Do you offer gift wrapping?**
A: Yes! Gift wrapping is available at checkout for $4.99. You can also include a personalized message.

## Account

**Q: How do I create an account?**
A: Click "Sign Up" in the top right corner. You can register with email or sign in with Google/Apple.

**Q: I forgot my password. What do I do?**
A: Click "Forgot Password" on the login page. A reset link will be sent to your email within 5 minutes.

**Q: How do I update my shipping address?**
A: Go to Account Settings > Addresses. You can save multiple addresses.

## Products

**Q: Are your products authentic?**
A: Yes, all products are 100% authentic and sourced directly from manufacturers or authorized distributors.

**Q: Do you restock sold-out items?**
A: Most items are restocked within 2-4 weeks. You can sign up for "Back in Stock" alerts on product pages.

**Q: Can I request a product that's not in your catalog?**
A: Yes! Use our Product Request form or contact support. We consider all suggestions.`,
    },
    {
        id: 'loyalty-program',
        name: 'Loyalty & Rewards Program',
        category: 'general',
        icon: 'BadgePercent',
        description: 'Points system, tiers, rewards, and how to earn and redeem benefits.',
        content: `# Loyalty & Rewards Program

## How It Works
- Earn points on every purchase
- Unlock exclusive tiers for bigger benefits
- Redeem points for discounts, free shipping, and gifts

## Earning Points
| Action | Points Earned |
|--------|--------------|
| Every $1 spent | 10 points |
| Create an account | 500 points |
| Write a review | 200 points |
| Refer a friend | 1,000 points |
| Birthday bonus | 500 points |

## Reward Tiers
| Tier | Points Required | Benefits |
|------|----------------|---------|
| Bronze | 0 | Base earning rate, birthday bonus |
| Silver | 5,000 | 1.5x points, free standard shipping |
| Gold | 15,000 | 2x points, free express shipping, early access to sales |
| Platinum | 50,000 | 3x points, free overnight shipping, exclusive products, personal shopper |

## Redeeming Points
- 1,000 points = $5 discount
- 2,500 points = $15 discount
- 5,000 points = $35 discount
- Points can also be redeemed for free products and exclusive merchandise

## Important Notes
- Points expire after 12 months of account inactivity
- Points cannot be transferred between accounts
- Tier status is evaluated quarterly`,
    },
    {
        id: 'promotions-discounts',
        name: 'Promotions & Discounts',
        category: 'general',
        icon: 'Megaphone',
        description: 'Current sales, discount codes, and promotional offers information.',
        content: `# Promotions & Discounts

## Current Offers
- **New Customer**: 15% off first order with code WELCOME15
- **Free Shipping**: On all orders over $50
- **Bundle & Save**: Buy 3+ items, get 10% off entire order
- **Refer a Friend**: Give $10, Get $10 for each successful referral

## Seasonal Sales
- **Spring Sale**: March (up to 30% off)
- **Summer Clearance**: July (up to 50% off)
- **Black Friday / Cyber Monday**: November (biggest discounts of the year)
- **End of Year Sale**: December (up to 40% off)

## Discount Stacking Rules
- Only one promo code per order
- Promo codes cannot be combined with sale prices
- Loyalty points can be used alongside promo codes
- Free shipping threshold applies after discounts

## Student & Military Discounts
- **Students**: 10% off with valid .edu email (via SheerID verification)
- **Military & First Responders**: 15% off with ID.me verification
- Available year-round, stackable with loyalty points

## Newsletter Subscribers
- Exclusive early access to sales (24 hours before public)
- Monthly subscriber-only coupon codes
- First to know about new product launches`,
    },
    {
        id: 'warranty-guarantee',
        name: 'Warranty & Guarantee',
        category: 'general',
        icon: 'FileText',
        description: 'Product warranty terms, satisfaction guarantee, and claims process.',
        content: `# Warranty & Satisfaction Guarantee

## Satisfaction Guarantee
- 30-day no-questions-asked return policy
- If you're not 100% satisfied, return for a full refund
- We cover return shipping for defective products

## Product Warranty
| Category | Warranty Period | Coverage |
|----------|----------------|----------|
| Electronics | 1 year | Manufacturing defects |
| Clothing | 6 months | Fabric defects, stitching |
| Home Goods | 1 year | Structural defects |
| Accessories | 6 months | Hardware/material defects |

## What's Covered
- Manufacturing defects
- Material failures under normal use
- Hardware malfunctions (electronics)
- Missing parts or components

## What's NOT Covered
- Normal wear and tear
- Damage from misuse, accidents, or modifications
- Cosmetic damage (scratches, dents from use)
- Products purchased from unauthorized retailers

## How to Make a Warranty Claim
1. Contact support with order number and issue description
2. Provide photos/video of the defect
3. We review and respond within 48 hours
4. If approved: replacement shipped or refund issued
5. No need to return the defective product for claims under $50`,
    },
];

// ─── Icon Map ────────────────────────────────────────────────────────────────
const ICON_MAP = {
    ShieldCheck,
    Package,
    Building2,
    HelpCircle,
    Truck,
    CreditCard,
    Clock,
    Users,
    FileText,
    Globe,
    Headphones,
    BadgePercent,
    BookOpen,
    Scale,
    ShoppingBag,
    Megaphone,
};

// ─── Category Config ─────────────────────────────────────────────────────────
const CATEGORIES = {
    all: { label: 'All Templates', bg: 'bg-slate-50', color: 'text-slate-600' },
    policies: { label: 'Policies', bg: 'bg-red-50', color: 'text-red-600' },
    products: { label: 'Products', bg: 'bg-blue-50', color: 'text-blue-600' },
    company_info: { label: 'Company Info', bg: 'bg-emerald-50', color: 'text-emerald-600' },
    general: { label: 'General', bg: 'bg-purple-50', color: 'text-purple-600' },
};

export default function KnowledgeTemplateGallery({ onSelectTemplate, onClose }) {
    const [activeCategory, setActiveCategory] = useState('all');
    const [selectedTemplate, setSelectedTemplate] = useState(null);

    const filteredTemplates = useMemo(() => {
        if (activeCategory === 'all') return KNOWLEDGE_TEMPLATES;
        return KNOWLEDGE_TEMPLATES.filter((t) => t.category === activeCategory);
    }, [activeCategory]);

    const handleUseTemplate = () => {
        if (!selectedTemplate) return;
        // Append short unique suffix so the slug never collides
        const suffix = Date.now().toString(36).slice(-4);
        onSelectTemplate({
            title: selectedTemplate.name,
            name: `${selectedTemplate.id}-${suffix}`, // Keeping for internal ID if needed
            description: selectedTemplate.description,
            category: selectedTemplate.category,
            content: selectedTemplate.content,
            always_on: false,
            is_active: true,
        });
    };

    return (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            {/* Header */}
            <div className="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <div className="flex items-center gap-3">
                    <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                        <BookOpen className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900 dark:text-white m-0">
                            {__('Knowledge Templates', 'smart-woo-chatbot')}
                        </h2>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {__('Start with pre-built knowledge and customize it for your business.', 'smart-woo-chatbot')}
                        </p>
                    </div>
                </div>
            </div>

            {/* Category Tabs */}
            <div className="border-b border-gray-200 dark:border-gray-700 px-6 bg-gray-50/50 dark:bg-gray-800/50">
                <nav className="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                    {Object.entries(CATEGORIES).map(([catId, cat]) => (
                        <button
                            key={catId}
                            type="button"
                            className={`whitespace-nowrap py-3.5 px-1 border-b-2 font-medium text-sm transition-colors ${activeCategory === catId
                                ? 'border-primary text-primary'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                                }`}
                            onClick={() => setActiveCategory(catId)}
                        >
                            {cat.label}
                            {catId !== 'all' && (
                                <span className="ml-1.5 text-xs text-gray-400">
                                    ({KNOWLEDGE_TEMPLATES.filter((t) => t.category === catId).length})
                                </span>
                            )}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Content: Template List + Preview */}
            <div className="flex flex-col lg:flex-row min-h-[520px]">
                {/* Template Grid */}
                <div className="w-full lg:w-1/2 border-r border-gray-200 dark:border-gray-700 p-5 overflow-y-auto max-h-[600px]">
                    <div className="grid grid-cols-1 gap-3">
                        {filteredTemplates.map((template) => {
                            const IconComp = ICON_MAP[template.icon] || FileText;
                            const catConfig = CATEGORIES[template.category] || CATEGORIES.general;
                            const isSelected = selectedTemplate?.id === template.id;

                            return (
                                <button
                                    key={template.id}
                                    type="button"
                                    className={`relative text-left w-full rounded-xl border p-4 transition-all focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 ${isSelected
                                        ? 'border-primary bg-primary/5 dark:bg-primary/10 ring-1 ring-primary shadow-sm'
                                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-primary/40 hover:bg-gray-50 dark:hover:bg-gray-700'
                                        }`}
                                    onClick={() => setSelectedTemplate(template)}
                                >
                                    <div className="flex items-start gap-3">
                                        {/* Icon */}
                                        <div
                                            className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${catConfig.bg} ${catConfig.color}`}
                                        >
                                            <IconComp size={20} />
                                        </div>

                                        {/* Info */}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <h4 className="text-sm font-semibold text-gray-900 dark:text-white m-0">
                                                    {template.name}
                                                </h4>
                                                <span
                                                    className={`inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full ${catConfig.bg} ${catConfig.color}`}
                                                >
                                                    {catConfig.label}
                                                </span>
                                            </div>
                                            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                                {template.description}
                                            </p>
                                        </div>

                                        {/* Selected indicator */}
                                        {isSelected && (
                                            <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary text-white">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                                                    <polyline points="20 6 9 17 4 12" />
                                                </svg>
                                            </div>
                                        )}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Preview Panel */}
                <div className="w-full lg:w-1/2 bg-gray-50 dark:bg-gray-900/50 p-5 flex flex-col">
                    {selectedTemplate ? (
                        <div className="h-full flex flex-col bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                            {/* Preview header */}
                            <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/50">
                                <h3 className="text-sm font-semibold text-gray-900 dark:text-white m-0">
                                    {__('Preview:', 'smart-woo-chatbot')} {selectedTemplate.name}
                                </h3>
                                <span className="text-xs text-gray-400">
                                    {selectedTemplate.content.length} {__('characters', 'smart-woo-chatbot')}
                                </span>
                            </div>

                            {/* Preview content */}
                            <div className="flex-1 p-4 overflow-y-auto">
                                <pre className="text-xs text-gray-600 dark:text-gray-300 font-mono whitespace-pre-wrap bg-gray-50 dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700 leading-relaxed">
                                    {selectedTemplate.content.substring(0, 1200)}
                                    {selectedTemplate.content.length > 1200 ? '\n\n... (truncated for preview)' : ''}
                                </pre>
                            </div>

                            {/* Actions */}
                            <div className="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    className="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
                                    onClick={onClose}
                                >
                                    {__('Cancel', 'smart-woo-chatbot')}
                                </button>
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-2 px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-primary hover:bg-primary/90 transition-colors"
                                    onClick={handleUseTemplate}
                                >
                                    <BookOpen size={14} />
                                    {__('Use This Template', 'smart-woo-chatbot')}
                                </button>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center justify-center h-full text-gray-500 dark:text-gray-400">
                            <div className="text-center">
                                <BookOpen className="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" />
                                <p className="text-sm">{__('Select a template to preview its contents', 'smart-woo-chatbot')}</p>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

KnowledgeTemplateGallery.propTypes = {
    onSelectTemplate: PropTypes.func.isRequired,
    onClose: PropTypes.func.isRequired,
};
